#!/bin/sh -e

c=8
f='domains.list'
n="$( wc -l "$f" | awk '{ print $1 }' )"
s="$( echo "$n / $c + 10" | bc )"
t='/tmp/matrix-scanner-'

rm -f "$t"*

split -l "$s" "$f" "$t"

for s in "$t"*
do
    [ ! -f "$s" ] && continue
    while read -r l
    do php scanner.php "$l"
    done < "$s" &
done
