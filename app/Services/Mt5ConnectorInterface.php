<?php
namespace App\Services;
interface Mt5ConnectorInterface { public function connect(array $credentials):bool; public function getAccountInfo():array; public function getOpenPositions():array; public function getTradeHistory():array; public function disconnect():void; }
