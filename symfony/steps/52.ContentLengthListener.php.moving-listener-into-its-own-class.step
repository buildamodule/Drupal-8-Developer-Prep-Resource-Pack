<?php
 
namespace Simplex;
 
class ContentLengthListener
{
  public function onResponse(ResponseEvent $event)
  {
    $response = $event->getResponse();
    $headers = $response->headers;
 
    if (!$headers->has('Content-Length') && !$headers->has('Transfer-Encoding')) {
      $headers->set('Content-Length', strlen($response->getContent()));
    }
  }
}