# 環境構築

## 初回にやること
### composer依存関係のインストール

参考: [v11 sail - Readouble](https://readouble.com/laravel/11.x/ja/sail.html#installing-composer-dependencies-for-existing-projects)

```shell
$ docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs
```

### sail

sailのエイリアス `./vendor/bin/sail` はご自分のすきなように
```shell
$ sail up -d --build #初回起動
```

### DB
```shell
$ sail artisan migrate:fresh --seed # テストデータのシーダ込みで
```

### npm
基本sail側のnpmを使う
```shell
$ sail npm i
```

## バックエンド側

sail起動
```shell
$ sail up -d
```

sail終了
```shell
$ sail down
```

## フロント側

開発中のホットリロード向け
```shell
$ sail npm run dev
```

ビルド
```shell
$ sail npm run build
```