<?php
echo "begin at " . date('Y-m-d H:i:s') . "\n";
define('MAX_DATA_SIZE',2048);
define('POOL_DATA_HEAD',4 + 2);
define('POOL_FORK',100);
define('WORKER_STATE_EXIT',1);
define('WORKER_STATE_BUSY',2);
define('WORKER_STATE_FREE',3);
define('WORKER_STATE_DATA',4);
define('WORKER_STATE_CLIENT_EXIT',5);

$blockids = array();
init_shm();
$workerid;

echo "cretate workeres \n";
// 产生子进程
for($workerid = 0 ; $workerid < POOL_FORK ; $workerid++){
	$pid = pcntl_fork();
	if( $pid == 0 ) {
			do_work();
			exit();
	}
}

echo "split tasks \n";
//分配任务
for($i = 0 ; $i < 1000; $i++){
	add_task($i);
}

echo "wait workers done \n";
//清理任务，给子进程发送退出信号
while(true){
	$exit_clients = 0;
	foreach($blockids as $workerid => $blockid){
		$state = get_state($workerid);
		if( $state == WORKER_STATE_FREE){
			set_state($workerid,WORKER_STATE_EXIT);
		}
		if($state == WORKER_STATE_CLIENT_EXIT){
			$exit_clients++;
		}
	}
	if($exit_clients == count($blockids)) break;
	echo "still exit workers $exit_clients \n";
	usleep(30);
}
echo "clean memorys \n";
clean_shm();
echo "end at " . date('Y-m-d H:i:s') . "\n";
exit(0);

function do_work(){
	global $workerid;
	while(true){
		$state = get_state($workerid);	
		echo  "worker  state " . $workerid . " " .  $state . "\n";
		switch($state){
			case WORKER_STATE_EXIT:
				echo  "worker " . $workerid . " over \n";
				clean_work($workerid);
				exit();
			case WORKER_STATE_DATA:
				echo  "worker " . $workerid . " get data \n";
				$data = get_work_data($workerid);
				do_real_work($data);
				break;
			case WORKER_STATE_FREE:
				echo  "worker " . $workerid . " no data sleep 1\n";
				usleep(10);
				break;
			default:
				exit();	
		}
	}
}

function clean_work($workerid){
	set_state($workerid,WORKER_STATE_CLIENT_EXIT);
	echo  "worker " . $workerid . " over \n";
	exit();
}

function do_real_work($data){
		global $workerid;
		echo  "worker " . $workerid . " do work \n";
		set_state($workerid,WORKER_STATE_BUSY);
		sleep(3);
		set_state($workerid,WORKER_STATE_FREE);
		echo  "worker " . $workerid . " do work over \n";
}

function add_task($data){
	do{
		$workerid = get_free_workerid();
		if($workerid != -1){
			set_work_data($workerid,$data);	
			break;	
		} 
		echo "not find free worker sleep \n";
		usleep(100);
	} while(true);
}

function get_free_workerid(){
		global $blockids;
		foreach($blockids as $workerid => $blockid){
			$state = get_state($workerid);
			if($state == WORKER_STATE_FREE){
				return $workerid;
			}
		}
		return -1;
}

function get_state($workerid){
		global $blockids;
		$data = shmop_read($blockids[$workerid],4,2);
		$m = unpack('S',$data);
		return $m[1];	
}

function set_state($workerid,$state){
		global $blockids;
		$m = pack('S',$state);
		shmop_write($blockids[$workerid],$m,4);
}

function get_work_data($workerid){
		global $blockids;
		$data = shmop_read($blockids[$workerid],POOL_DATA_HEAD,MAX_DATA_SIZE);
		return $data;
}

function set_work_data($workerid,$data){
		write_block($workerid,WORKER_STATE_DATA,$data);
}

function write_block($workerid,$state,$data){
		global $blockids;
		$data_str = serialize($data);
    if(strlen($data_str) >= MAX_DATA_SIZE){
        echo "TOO BIG DATA\n";
        return false;
    } else {
        $data_str = str_pad($data_str,MAX_DATA_SIZE,' ');    
    }
    $memory_data = $data_str;
		$memory_data = pack('IS',$workerid,$state) . $memory_data;
		shmop_write($blockids[$workerid],$memory_data,0);
}

function init_shm(){
		global $blockids;
		for($i = 0 ; $i < POOL_FORK ; $i++){
			$systemid = ftok('./pools/' . $i , 't');		
			$mode = 'c';	
			$permissions = '0755';
			$size = POOL_DATA_HEAD + MAX_DATA_SIZE;
			$blockids[$i] = shmop_open($systemid,$mode,$permissions,$size); 
			//echo $blockids[$i] . "\n";
			write_block($i,WORKER_STATE_FREE,"");
		} 
}

function clean_shm($workerid = -1 ){
		global $blockids;
		if($workerid != -1 ) { 
			return shmop_delete($blockids[$workerid]);
		} else {
			foreach($blockids as $task_id=>$shmid){
				shmop_delete($shmid);
			}
		}
}
