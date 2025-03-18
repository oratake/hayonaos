composer依存関係のインストール
参考: [v11 sail - Readouble](https://readouble.com/laravel/11.x/ja/sail.html#installing-composer-dependencies-for-existing-projects)

```
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs
```
