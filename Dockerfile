FROM ubuntu:16.04

RUN apt-get update && apt-get install -y --no-install-recommends apt-utils && apt-get -y clean

RUN apt-get -y update && apt-get -y upgrade && apt-get -y install \
        imagemagick \
        tesseract-ocr \
        tesseract-ocr-deu \
        wget \
        lighttpd \
        php-cgi \
        php-curl \
        && \
        apt-get -y clean

RUN cp /etc/lighttpd/conf-available/05-auth.conf /etc/lighttpd/conf-enabled/
RUN cp /etc/lighttpd/conf-available/15-fastcgi-php.conf /etc/lighttpd/conf-enabled/
RUN cp /etc/lighttpd/conf-available/10-fastcgi.conf /etc/lighttpd/conf-enabled/
RUN mkdir -p /var/run/lighttpd
RUN touch /var/run/lighttpd/php-fastcgi.socket
RUN chown -R www-data /var/run/lighttpd

RUN mkdir -p /var/www/html/uploads
RUN chmod -R 777 /var/www/html/uploads

RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 2G/g' /etc/php/7.0/cgi/php.ini

EXPOSE 80

ADD ocr.php /var/www/html
ADD policy.xml /etc/ImageMagick-6/

CMD ["lighttpd", "-D", "-f", "/etc/lighttpd/lighttpd.conf"]
