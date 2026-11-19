<?php
return [
 'default'=>env('LOG_CHANNEL','stack'),
 'deprecations'=>['channel'=>'null','trace'=>false],
 'channels'=>[
 'stack'=>['driver'=>'stack','channels'=>['daily'],'ignore_exceptions'=>false],
 'daily'=>['driver'=>'daily','path'=>storage_path('logs/laravel.log'),'level'=>env('LOG_LEVEL','warning'),'days'=>14,'replace_placeholders'=>true],
 'single'=>['driver'=>'single','path'=>storage_path('logs/laravel.log'),'level'=>'debug'],
 'stderr'=>['driver'=>'monolog','handler'=>Monolog\Handler\StreamHandler::class,'with'=>['stream'=>'php://stderr']],
 'null'=>['driver'=>'monolog','handler'=>Monolog\Handler\NullHandler::class],
 ],
];
