#!/bin/sh

ps aux | grep m.php | grep -v grep | awk '{print $2}' | xargs kill 
