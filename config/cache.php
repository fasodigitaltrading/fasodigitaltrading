<?php
return ['default'=>env('CACHE_STORE','file'),'stores'=>['file'=>['driver'=>'file','path'=>storage_path('framework/cache/data')],'database'=>['driver'=>'database','table'=>'cache','connection'=>env('DB_CACHE_CONNECTION')]],'prefix'=>env('CACHE_PREFIX','blackwolf')];
