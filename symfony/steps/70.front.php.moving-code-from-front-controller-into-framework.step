<?php
 
require_once __DIR__ . '/vendor/autoload.php';
 
use Symfony\Component\HttpFoundation\Request;
 
$request = Request::createFromGlobals();
$routes = include __DIR__ . '/src/app.php';
 
$framework = new Simplex\Framework($routes);
 
$framework->handle($request)->send();