FROM ubuntu:22.04

RUN DEBIAN_FRONTEND=noninteractive TZ=Etc/UTC apt-get update && apt-get -y install tzdata && apt-get -y clean

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

RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 2G/g' /etc/php/8.1/cgi/php.ini
RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 2G/g' /etc/php/8.1/cli/php.ini

RUN sed -i 's/post_max_size = 8M/post_max_size = 0/g' /etc/php/8.1/cgi/php.ini
RUN sed -i 's/post_max_size = 8M/post_max_size = 0/g' /etc/php/8.1/cli/php.ini

EXPOSE 80

ADD index.html /var/www/html
ADD ocr.php /var/www/html
ADD policy.xml /etc/ImageMagick-6/
RUN chown -R www-data /var/www/

CMD ["lighttpd", "-D", "-f", "/etc/lighttpd/lighttpd.conf"]
