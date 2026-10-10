FROM ubuntu:22.04

RUN DEBIAN_FRONTEND=noninteractive TZ=Etc/UTC apt-get update && apt-get -y install tzdata && apt-get -y clean

# ocrmypdf keeps the original page images and adds a text layer; tesseract
# language packs: German by default, English comes with tesseract-ocr itself.
# Add further tesseract-ocr-<lang> packages here to make them available via
# the "lang" form field.
RUN apt-get -y update && apt-get -y install --no-install-recommends \
        ocrmypdf \
        tesseract-ocr \
        tesseract-ocr-deu \
        ghostscript \
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

EXPOSE 80

ADD index.html /var/www/html
ADD ocr.php /var/www/html
RUN chown -R www-data /var/www/

CMD ["lighttpd", "-D", "-f", "/etc/lighttpd/lighttpd.conf"]
