<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VoicePreviewController extends ControllerBase
{
  public function downloadPreview(Request $request, $file)
  {
    $uri = 'private://voice-previews/' . $file;
    if (!file_exists($uri)) {
      throw new NotFoundHttpException();
    }

    $response = new BinaryFileResponse($uri);
    $response->headers->set('Content-Type', 'audio/mpeg');
    return $response;
  }
}
