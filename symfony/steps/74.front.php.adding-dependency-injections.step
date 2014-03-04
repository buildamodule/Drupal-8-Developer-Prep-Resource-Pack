<?php

require_once __DIR__ . '/vendor/autoload.php';
 
use Symfony\Component\HttpFoundation\Request;
 
$routes = include __DIR__ . '/src/app.php';
$sc = include __DIR__ . '/src/container.php';
 
$request = Request::createFromGlobals();
 
$response = $sc->get('framework')->handle($request);
 
$response->send();