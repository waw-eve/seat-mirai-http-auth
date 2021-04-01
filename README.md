![SeAT](http://i.imgur.com/aPPOxSK.png)

# SeAT Mirai Auth

This plugin provide an extension to the standard SeAT character Job which handle automatic graduation for QQ user.

This plugin is modify from [warlof/seat-teamspeak](https://github.com/warlof/seat-teamspeak).

## Installation

### for non-Docker

Assume your SeAT root path is `/var/www/seat` and run this code

```php
php artisan down
composer require warlof/seat-teamspeak

php artisan vendor:publish --force --all
php artisan migrate
php artisan up
```

### for Docker

Edit your `.env` file,locate the line `SEAT_PLUGINS` and append `kagurazakanyaa/seat-mirai-http-auth` at the end.

Then , run `docker-compose up -d` to take effect.
