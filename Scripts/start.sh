#!/bin/bash
function configLine {
    local OLD_LINE_PATTERN=$1
    local NEW_LINE=$2
    local FILE=$3
    local NEW=$(echo "${NEW_LINE}" | sed 's/\//\\\//g')
    touch "${FILE}"
    #echo "Find: $1\n Replace: $2\n in $3"
    sed -i '/'"${OLD_LINE_PATTERN}"'/{s/.*/'"${NEW}"'/;h};${x;/./{x;q100};x}' "${FILE}"
    if [[ $? -ne 100 ]] && [[ ${NEW_LINE} != '' ]]
    then
        echo "Error replacing ${OLD_LINE_PATTERN} for ${NEW} on file $FILE."
        #echo "${NEW_LINE}" >> "${FILE}"
    fi
}

SHELL=/bin/bash
BASH_ENV=/etc/environment
#timedatectl set-timezone America/Sao_Paulo

export PathMainsite="/var/www/html/public_html/wp-config.php"

_find='DB_NAME'
_replace="define('DB_NAME', '$RDS_DATABASE' );"
configLine "$_find" "$_replace" "$PathMainsite"

_find='DB_USER'
_replace="define('DB_USER', '$RDS_ADMIN' );"
configLine "$_find" "$_replace" "$PathMainsite"

_find='DB_PASSWORD'
_replace="define('DB_PASSWORD', '$RDS_PASSWORD' );"
configLine "$_find" "$_replace" "$PathMainsite"

_find='DB_HOST'
_replace="define('DB_HOST', '$RDS' );"
configLine "$_find" "$_replace" "$PathMainsite"

_find='table_prefix'
_replace="\$table_prefix  = '$TABLE_PREFIX';"
configLine "$_find" "$_replace" "$PathMainsite"

_find='WP_CACHE_KEY_SALT'
_replace="define('WP_CACHE_KEY_SALT','$ELASTICACHE_PREFIX');"
configLine "$_find" "$_replace" "$PathMainsite"

_find='WP_REDIS_HOST'
_replace="define('WP_REDIS_HOST', '$ELASTICACHE');"
configLine "$_find" "$_replace" "$PathMainsite"

_find='WP_HOME'
_replace="define('WP_HOME', '$WP_HOME');"
configLine "$_find" "$_replace" "$PathMainsite"

_find='WP_SITEURL'
_replace="define('WP_SITEURL', '$WP_HOME');"
configLine "$_find" "$_replace" "$PathMainsite"

DIR="$(dirname "${PathMainsite}")"
cd "$DIR/wp-content"
#rm -rf uploads

### links sometimes are problematic when using docker.
### Map directly on container specs at task definition
#ln -s /mnt/efs/uploads/ uploads
cd $DIR
wp core update --allow-root
wp plugin update --all --allow-root

wp db size --allow-root 

if [ $? -eq 0 ] 
then 
    wp plugin activate redis-cache --allow-root
    wp redis enable --force --allow-root
    wp cache flush --allow-root
else 
  echo "Could not connect to database. Maybe it's the first run ..." 
fi

# echo "<html><body><p>ok</p></body></html>" > /var/www/html/public_html/health.html
# chown apache:apache /var/www/html/public_html -R

# export croncmd="/usr/bin/php /var/www/html/public_html/admin/cli/cron.php >/dev/null 2>&1"
# export cronjob="* * * * * $croncmd"
# ( crontab -l | grep -v -F "$croncmd" ; echo "$cronjob" ) | crontab -
# crond -n &

#apachectl restart
httpd -D BACKGROUND
php-fpm -F