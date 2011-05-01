#!/bin/sh
# $Id$

if [ "$1" = "doc" ]; then
	phpdoc -s on -p on -o HTML:Smarty:PHP -f ePrint.php -t docs -ti "ePrint pear package Reference"
	exit $?
fi

cp -af package.xml.tmpl package.xml
list=$(grep "md5sum" ./package.xml | sed 's/.*"@\|@".*//g')

for i in $list
do
	md5s=$(md5sum $i | awk '{print $1}')
	perl -pi -e "s!\@${i}\@!${md5s}!g" ./package.xml
done

curdate=$(date +'%Y-%m-%d')
curtime=$(date +'%H:%M:%S')

perl -pi -e "s!\@curdate\@!${curdate}!g" ./package.xml
perl -pi -e "s!\@curtime\@!${curtime}!g" ./package.xml

[ -z "$1" ] && pear package
