<?php

require_once __DIR__ . '/vendor/autoload.php';
 
use Symfony\Component\HttpFoundation\Request;

$sc = include __DIR__ . '/src/container.php';
 
$request = Request::createFromGlobals();
 
$response = $sc->get('framework')->handle($request);
 
$response->send();