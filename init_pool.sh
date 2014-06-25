#!/bin/sh

mkdir -p pools
for((i=0;i<100;i++))
do
	mkdir pools/$i
done
