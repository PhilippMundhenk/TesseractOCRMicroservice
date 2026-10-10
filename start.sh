#!/bin/sh
# lighttpd does not hand the container environment to php-cgi, so the OCR_*
# settings are written to a config file that ocr.php reads.
set -e
{
  echo '<?php'
  echo 'return array('
  for name in OCR_LANGUAGE OCR_JOBS OCR_RENDER_DPI OCR_JPEG_QUALITY; do
    eval "value=\${$name:-}"
    escaped=$(printf '%s' "$value" | sed "s/'/\\\\'/g")
    echo "  '$name' => '$escaped',"
  done
  echo ');'
} > /etc/ocr-env.php
chmod 644 /etc/ocr-env.php
echo "OCR settings: $(grep "=>" /etc/ocr-env.php | tr -d "\n" | tr -s ' ')"
exec lighttpd -D -f /etc/lighttpd/lighttpd.conf
