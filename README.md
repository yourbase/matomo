# Matomo on Heroku with AWS RDS

Optimised for multiple workers and auto archiving. Inspired by [creativecoder/piwik-heroku](https://github.com/creativecoder/piwik-heroku).

## Setup a Deploy

[Install the heroku cli](https://devcenter.heroku.com/articles/heroku-cli).

1. `heroku apps:create my-matomo --region eu`
  - `heroku buildpacks:add --index 1 https://github.com/danstiner/heroku-buildpack-geoip-geolite2`
  - `heroku buildpacks:add --index 2 heroku/php`
2. Add a `MYSQL_URL`, e.g. AWS RDS
3. Add a `REDIS_URL`, e.g. `heroku addons:create heroku-redis:premium-1`
4. `heroku config:set SALT=XXXXXXX TRUSTED_HOST=my-matomo.herokuapp.com`
5. `git push heroku`

## Config

`generate.config.ini.php` is run before starting the app.

### Local Config

```bash
REDIS_URL=redis://127.0.0.1:6379 \
MYSQL_URL=mysql://root:@localhost:3306/piwik \
TRUSTED_HOST=localhost:8000 \
SALT=XXXXXXX \
php ./generate.config.ini.php
```

### Running Locally

```bash
php -S 0.0.0.0:8000 -t matomo/
```

## Archiving

Rebuild all reports:

```bash
# scale up web app since archiver access them
heroku ps:scale web=2:performance-m
# run detached to avoid timeout
heroku run:detached --size=performance-l "php ./generate.config.ini.php && php -d memory_limit=-1 ./matomo/console core:archive --url=https://my-matomo.herokuapp.com/ --force-all-websites --concurrent-requests-per-website 8"
heroku ps # get run number, e.g. 1
# follow logs
heroku logs --dyno run.1 -t
# stop if needed
heroku ps:stop run.1
# scale down
heroku ps:scale web=1:standard-2x
```

See [matomo docs](https://matomo.org/docs/setup-auto-archiving/) for more options.

### Scheduler

Add the «Heroku Scheduler» addon and setup a job to run the following command every hour at 10 past with an performance-l dyno:

```
php ./generate.config.ini.php && php -d memory_limit=-1 ./matomo/console core:archive --url=https://my-matomo.herokuapp.com/
```

## Plugins

Run it locally and install via the interface.

Afterwards synch the newly added or removed plugins manually to `Plugins[]` and `PluginsInstalled[]` in `generate.config.ini.php`.

## GeoIP

This setup is configured to use the GeoIp2 plugin included in the core Matomo package. The GeoLite databases are downloaded using a custom buildpack https://github.com/danstiner/heroku-buildpack-geoip-geolite2 defined in `.buildpacks`.

You can turn on this geolocation method on in Settings > System > Geolocation. Rebuilding the app will get a fresh copy of the GeoLite databases. You can also configure the plugin to download an updated database periodically.
