<?php

return [
  'max_upload_bytes' => (int) env('MEDIA_MAX_UPLOAD_BYTES', 10 * 1024 * 1024),
  'max_dimension' => (int) env('MEDIA_MAX_DIMENSION', 1920),
  'thumb_dimension' => (int) env('MEDIA_THUMB_DIMENSION', 320),
  'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 85),
  'lightbox_min_dimension' => (int) env('MEDIA_LIGHTBOX_MIN_DIMENSION', 600),
  'allowed_mimes' => [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
  ],
];
