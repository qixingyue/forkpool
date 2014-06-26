<?php

$run_file = __FILE__ . '.run';
if($fp = fopen($run_file,'a')){
	if(flock($fp,LOCK_EX | LOCK_NB)){
		echo "RUNNING \n";
		sleep(30);

		flock($fp,LOCK_UN);
		fclose($fp);
		exit();
	}
	echo "LOCK EXIT";
}
