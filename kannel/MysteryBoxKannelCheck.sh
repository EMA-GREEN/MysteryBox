#!/bin/bash


#subject="Tigo MysteryBox Application Alert"
#from="Server Alert <support@greentelecom.co.tz>"
## sending mail to

to="Emanuel Julius<emanuel@greentelecom.co.tz>"

while [ : ]
do

brbx=`ps -eaflc --sort stime | grep "bearerbox mysterybox.conf" |grep -v grep | wc -l`

if(test $brbx -lt 1) ;
then
    echo "MysteryBox bearerbox is Down ..."
    nohup /usr/local/kannel/sbin/bearerbox mysterybox.conf &
    echo "MysteryBox bearerbox Started...."

else
    echo "MysteryBox bearerbox is  Running"
fi

smsbx=`ps -eaflc --sort stime | grep "smsbox mysterybox.conf" |grep -v grep | wc -l`

if(test $smsbx -lt 1) ;
then
    echo "MysteryBox Smsbox  is Down ..."
    nohup /usr/local/kannel/sbin/smsbox mysterybox.conf &
    echo "MysteryBox Sms box  Started...."

else
    echo "MysteryBox Smsbox is  Running...."
fi

brbx_entice=`ps -eaflc --sort stime | grep "bearerbox mysteryboxentice.conf" |grep -v grep | wc -l`

if(test $brbx_entice -lt 1) ;
then
    echo "MysteryBoxEntice bearerbox is Down ..."
    nohup /usr/local/kannel/sbin/bearerbox mysteryboxentice.conf &
    echo "MysteryBoxEntice bearerbox Started...."

else
    echo "MysteryBoxEntice bearerbox is  Running"
fi

smsbx_entice=`ps -eaflc --sort stime | grep "smsbox mysteryboxentice.conf" |grep -v grep | wc -l`

if(test $smsbx_entice -lt 1) ;
then
    echo "MysteryBoxEntice Smsbox  is Down ..."
    nohup /usr/local/kannel/sbin/smsbox mysteryboxentice.conf &
    echo "MysteryBoxEntice Sms box  Started...."

else
    echo "MysteryBoxEntice Smsbox is  Running...."
fi

sleep 3
done
