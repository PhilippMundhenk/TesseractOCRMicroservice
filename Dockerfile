FROM ubuntu:22.04

RUN DEBIAN_FRONTEND=noninteractive TZ=Etc/UTC apt-get update && apt-get -y install tzdata && apt-get -y clean

# ocrmypdf adds the text layer; ghostscript + img2pdf re-render pages as JPEG
# (OCR_RENDER_DPI) so OCR'd scans stay small; poppler-utils inspects the input.
# tesseract language packs: German by default, English comes with tesseract-ocr.
# Add further tesseract-ocr-<lang> packages here to make them available via
# the "lang" form field.
RUN apt-get -y update && apt-get -y install --no-install-recommends \
        ocrmypdf \
        tesseract-ocr \
        tesseract-ocr-deu \
        ghostscript \
        img2pdf \
        poppler-utils \
        lighttpd \
        php-cgi \
        && \
        apt-get -y clean && rm -rf /var/lib/apt/lists/*

RUN cp /etc/lighttpd/conf-available/05-auth.conf /etc/lighttpd/conf-enabled/
RUN cp /etc/lighttpd/conf-available/15-fastcgi-php.conf /etc/lighttpd/conf-enabled/
RUN cp /etc/lighttpd/conf-available/10-fastcgi.conf /etc/lighttpd/conf-enabled/
RUN mkdir -p /var/run/lighttpd
RUN touch /var/run/lighttpd/php-fastcgi.socket
RUN chown -R www-data /var/run/lighttpd

RUN mkdir -p /var/www/html/uploads
RUN chmod -R 777 /var/www/html/uploads

RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 2G/g' /etc/php/8.1/cgi/php.ini
RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 2G/g' /etc/php/8.1/cli/php.ini

RUN sed -i 's/post_max_size = 8M/post_max_size = 0/g' /etc/php/8.1/cgi/php.ini
RUN sed -i 's/post_max_size = 8M/post_max_size = 0/g' /etc/php/8.1/cli/php.ini

# OCR of a many-page document takes a while; do not let PHP give up on it.
RUN sed -i 's/max_execution_time = 30/max_execution_time = 1800/g' /etc/php/8.1/cgi/php.ini

ENV OCR_LANGUAGE=deu
ENV OCR_JOBS=2
# Re-render pages as JPEG at this dpi before OCR (0 keeps the original images).
ENV OCR_RENDER_DPI=300
ENV OCR_JPEG_QUALITY=85

EXPOSE 80

ADD index.html /var/www/html
ADD ocr.php /var/www/html
ADD start.sh /start.sh
RUN chmod 755 /start.sh
RUN chown -R www-data /var/www/

CMD ["/start.sh"]
