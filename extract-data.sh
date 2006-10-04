#!/bin/bash
#
# Script to extract user data from the databases to csv files
# It takes a bit more than one hour to extract the data.
#
# Author: Brion Vibber

cluster=`cat /etc/cluster`
for db in `cat /home/wikipedia/common/$cluster.dblist`
do
  if [ "$cluster" == "yaseo" ]
  then
    server=dryas
  else
    if [ "$db" == "enwiki" ]
    then
      server=db4
    else
      server=samuel
    fi
  fi
  echo "User data from $db..."
  echo "SELECT user_id, user_name, user_email, user_email_authenticated FROM user;" | \
    sql $db -h $server > $db-user.csv
  echo "Counts from $db..."
  echo "SELECT rev_user, count(*) FROM revision GROUP BY rev_user;" | \
    sql $db -h $server > $db-count.csv
done


# 1) SELECT user_id, user_name, user_email, user_email_authenticated FROM user;
# 2) SELECT rev_user, count(*) FROM revision GROUP BY rev_user;
#
# SELECT user_id, user_name, user_email, user_email_authenticated, COUNT(rev_user) as editcount
# FROM user LEFT OUTER JOIN revision ON user_id=rev_user GROUP BY user_id, rev_user;
