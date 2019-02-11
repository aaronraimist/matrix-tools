#!/bin/sh -e

total="$( sqlite3 scanner.db 'select count() from scanner where datetime( last_time ) > datetime( "now", "-1 day" )' )"
wellk="$( sqlite3 scanner.db 'select count() from scanner where datetime( last_time ) > datetime( "now", "-1 day" ) and well_known' )"
srvre="$( sqlite3 scanner.db 'select count() from scanner where datetime( last_time ) > datetime( "now", "-1 day" ) and srv_record' )"

__report() {
    date -R
    echo "$total homeservers online"
    echo "$wellk use .well-known, $srvre srv record"
    echo
    
    sqlite3 scanner.db \
    'select
        distinct( software ) as v,
        count() as c
    from
        scanner
    where
        datetime( last_time ) > datetime( "now", "-1 day" )
    group by v
    order by c desc, v asc' \
    | awk -F'|' '{print $2"|"$1}' \
    | column -t -s '|'
}

__report > report.txt
