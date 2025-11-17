# kintai(coachtech 勤怠管理アプリ)

## 環境構築

1.git clone https://github.com/hirata21/kintai.git

2.DockerDesktop アプリを立ち上げる

3.docker-compose up -d --build

4.docker-compose exec php bash

5.composer install

6.「.env.example」ファイルを 「.env」ファイルに命名を変更。

7..env に以下の環境変数を追加

DB_CONNECTION=mysql

DB_HOST=mysql

DB_PORT=3306

DB_DATABASE=laravel_db

DB_USERNAME=laravel_user

DB_PASSWORD=laravel_pass

5.アプリケーションキーの作成

php artisan key:generate

6.マイグレーションの実行

php artisan migrate

7.シーディングの実行

php artisan db:seed

## メール認証

開発環境では MailHog を使用しています。
コンテナ起動後、以下のURLからメールボックスを確認できます。

http://localhost:8025

`.env` のメール関連設定は、以下のようにしてください。

MAIL_MAILER=smtp
MAIL_HOST=mailhog   # docker-compose のサービス名に合わせる
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=example@example.com  # 任意のメールアドレス
MAIL_FROM_NAME="${APP_NAME}"

ユーザー登録を行うと、
MailHog の画面 (http://localhost:8025) に確認メールが届きます。

## テーブル仕様書

### usersテーブル

| カラム名           | 型            | primary key | unique key | not null | foreign key |
|-------------------|---------------|-------------|------------|-----------|--------------|
| id                | bigint        | ○           |            | ○        |              |
| name              | varchar(255)  |             |            | ○        |              |
| email             | varchar(255)  |             | ○          | ○        |              |
| password          | varchar(255)  |             |            | ○        |              |
| email_verified_at | timestamp     |             |            |          |              |
| role              | enum          |             |            | ○        |              |
| remember_token    | varchar(100)  |             |            |          |              |
| created_at        | timestamp     |             |            |          |              |
| updated_at        | timestamp     |             |            |          |              |

### attendancesテーブル

| カラム名       | 型              | primary key | unique key | not null | foreign key     |
|----------------|-----------------|-------------|------------|-----------|------------------|
| id             | unsigned bigint | ○           |            | ○        |                  |
| user_id        | unsigned bigint |             |            | ○        | users(id)        |
| work_date      | date            |             |            | ○        |                  |
| start_at       | datetime        |             |            |          |                  |
| end_at         | datetime        |             |            |          |                  |
| break_minutes  | unsigned int    |             |            |          |                  |
| status         | enum            |             |            | ○        |                  |
| note           | varchar(255)    |             |            |          |                  |
| created_at     | timestamp       |             |            |          |                  |
| updated_at     | timestamp       |             |            |          |                  |

### breaksテーブル

| カラム名       | 型              | primary key | unique key | not null | foreign key       |
|----------------|-----------------|-------------|------------|-----------|--------------------|
| id             | unsigned bigint | ○           |            | ○        |                    |
| attendance_id  | unsigned bigint |             |            | ○        | attendances(id)    |
| start_at       | datetime        |             |            | ○        |                    |
| end_at         | datetime        |             |            |          |                    |
| created_at     | timestamp       |             |            |          |                    |
| updated_at     | timestamp       |             |            |          |                    |

### requestsテーブル

| カラム名        | 型              | primary key | unique key | not null | foreign key       |
|-----------------|-----------------|-------------|------------|-----------|--------------------|
| id              | unsigned bigint | ○           |            | ○        |                    |
| user_id         | unsigned bigint |             |            | ○        | users(id)          |
| attendance_id   | unsigned bigint |             |            | ○        | attendances(id)    |
| status          | enum            |             |            | ○        |                    |
| payload_before  | json            |             |            |          |                    |
| payload_current | json            |             |            |          |                    |
| created_at      | timestamp       |             |            |          |                    |
| updated_at      | timestamp       |             |            |          |                    |

## ER 図

![ER図](ER.png)

## 開発用ログインアカウント

以下のユーザーは `php artisan db:seed` で自動作成されます。

### 管理者
name: 管理者 太郎
email: admin@example.com
password: password123

### 一般ユーザー
name: ユーザー
email: user@example.com
password: password123

name: 山田 太郎
email: taro@example.com
password: password123

name: 佐藤 次郎
email: jiro@example.com
password: password123

## PHPUnit テストについて

//テスト用データベースの作成

docker-compose exec mysql bash

mysql -u root -p

//パスワードはrootと入力

create database test_database;

//.env.testingの作成

docker-compose exec php bash

cp .env .env.testing

APP_ENV=test

DB_DATABASE=test_database

DB_USERNAME=root

DB_PASSWORD=root

php artisan key:generate --env=testing

php artisan config:clear

php artisan migrate:fresh --env=testing

php artisan test

※.env.testingにもmailhogの設定をしてください。