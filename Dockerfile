################# IMPORTANT IMPORTANT IMPORTANT
########
######## DO NOT FORGET TO ADD imagedfinitions.json do the root folder with Dockerfile
########
######## Without this file it's not possible to update de ECS with the latest image using pipeline
########
#################

## Change to Add Cloudwatch log monitoring
## Docker does not run daemons, cloudwatch will not work in its standard setup
## Instead use the cloudwatch driver on task definition
## and redirect the logs to the "console" to be captured
##      https://docs.aws.amazon.com/AmazonECS/latest/developerguide/using_awslogs.html
##      https://docs.docker.com/config/containers/logging/
## Changes were made on httpd.conf to match these requirements
## /proc/1/fd/1 is stdout (yes it does not match docker documentation directly)
## /proc/1/fd/2 is stderr (yes it does not match docker documentation directly)
## The filtering must be made as usual as in cloudwatch docs

# Base image
FROM amazonlinux
WORKDIR /App
ARG logGroup=WP-CURSOS

# Install stuff
RUN yum -y update
RUN dnf update -y

### Changes for PHP 8.0
# RUN amazon-linux-extras enable php8.0 epel
# RUN amazon-linux-extras install php8.0 epel
# RUN yum clean metadata &&  yum install epel-release
RUN  dnf -y install php-{bcmath,cli,common,fpm,gd,intl,ldap,mbstring,mysqlnd,opcache,pdo,process,soap,xml,zip,devel,pear} \
httpd ghostscript git ImageMagick ImageMagick-devel gcc make \
nfs-utils cronie tar gzip patch unzip \
yum-utils && \
yum clean all &&  rm -rf /var/cache/yum

### Moodle asks for (doesn't require) XML RPC that it's not part of PHP anymore
### It's available as Release Candidate 3 only (2021-12-04)
### This package is not maintained (as in 2022-10-12 at https://pecl.php.net/package/xmlrpc)
### Add the following below     yes '' | pecl install channel://pecl.php.net/xmlrpc-1.0.0RC3
### using the same structure if xmlrpc is really needed

RUN pecl channel-update pecl.php.net && \
    yes '' | pecl install redis && \
    yes '' | pecl install igbinary && \
    # yes '' | pecl install memcached && \
    # yes '' | pecl install memcache && \
    yes '' | pecl install imagick 

##### End Changes for PHP 8.0

##### RUN curl -sS https://getcomposer.org/installer |  php && mv composer.phar /usr/local/bin/composer && ln -s /usr/local/bin/composer /usr/bin/composer

# Create folders
RUN mkdir -p /var/log/www/{error,access} ; \
    mkdir -p /var/www/html/public_html ; \
    mkdir -p /mnt/efs ; \
    mkdir -p /run/php-fpm ; \
    rm -rf /var/www/html/public_html/wp-content/uploads ; \
    mkdir -p /var/www/html/public_html/wp-content/uploads
#RUN mkdir -p /var/www/html/mdl-cache  #Apenas para Container com Moodle System

# Copy files and app
COPY ./Config/php.ini /etc/php.ini 
COPY ./Config/httpd.conf /etc/httpd/conf/httpd.conf

### List modules and extensions added (it's for log and troubleshotting as some extensions are build and
### MUST BE INSERTED ON PHP.INI
RUN php -m && php -i

# Must have de / at end and obviously be a folder
# To copy recursively don't use wildcards like *
COPY --chown=apache:apache ./Config/health.html /var/www/html/public_html/
RUN echo $(date) >> /var/www/html/public_html/health.html && chown apache:apache /var/www/html -R
COPY --chown=apache:apache App/ /var/www/html/public_html/
COPY --chown=apache:apache ./Config/wp-config.php /var/www/html/public_html/

### Tool to help fix Wordpress stuff on command line like clear cache in case of crazyness
### This tool does a lot of usefull things
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    cp wp-cli.phar /usr/local/bin/wp

RUN chown apache:apache /mnt/efs -R
RUN chown apache:apache /var/www/html/public_html/wp-content/ * -R
COPY ./Scripts/start.sh /start.sh
RUN chmod 755 /start.sh

### Final cleanup (yes must be more than once, it's on docs)
### Check this section if changes were made on install phase 
RUN yum remove -y `package-cleanup --leaves` ; \
    yum remove -y `package-cleanup --leaves` ; \
    yum remove -y `package-cleanup --leaves` ; \
    yum remove -y yum-utils 

EXPOSE 80
CMD ["/bin/bash", "/start.sh"]