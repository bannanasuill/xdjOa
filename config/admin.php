<?php

return [

    /*
    | 后台新增用户未填写密码时使用（须至少 6 位，与校验规则一致）。
    | 若曾执行过 php artisan config:cache，修改 .env 后须 config:clear 再缓存，否则会读不到新值。
    */
    'default_user_password' => env('DEFAULT_USER_PASSWORD', '123456'),

];
