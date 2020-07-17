<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\dropbox_utility;

class update extends Model
{

  public function status(){
    $result = storage_path()."/app/status";
    return $result;
  }

  public function webhook(){
    $update_object = new update;
    $dropbox_utility_object = new dropbox_utility;

    $webhook = $update_object->status()."/"."webhook.txt";

    $signal_status = "";

    if (isset($_GET['challenge'])) {
      $signal_status = "signal_test_passed";

      $result = $_GET['challenge'];
      // $timestamp = date('Y-m-d h:i:s a', time());
      // file_put_contents(
      //   $webhook,
      //   "ready"." ".$timestamp
      // );
      file_put_contents($webhook, "pending");
      return $result;

    } elseif ($dropbox_utility_object->authenticate() == 1) {
      $signal_status = "signal_security_passed";

      file_put_contents($webhook, "pending");

    } else {
      $signal_status = "signal_security_failed";

      header('HTTP/1.0 403 Forbidden');
      // file_put_contents($webhook, "not_ready");

    }

  }

  public function sync($update_object, $dropbox_utility_object){

    // $result = $dropbox_utility_object->dropbox_get_request("", $update_object, "files/list_folder");
    // dd($result);

    $time_i = strtotime("now");

    $diff = $update_object->status()."/"."diff.txt";
    $diff = $dropbox_utility_object->file_get_utf8($diff);

    $webhook_path = $update_object->status()."/"."webhook.txt";
    $webhook = $dropbox_utility_object->file_get_utf8($webhook_path);
    $webhook = preg_replace('/\s+/', '', $webhook);
    file_put_contents($webhook_path, "done");

    $proc_promise = $update_object->status()."/"."proc_promise.txt";
    $proc_promise = $dropbox_utility_object->file_get_utf8($proc_promise);
    $proc_promise = preg_replace('/\s+/', '', $proc_promise);

    $init_promise = $update_object->status()."/"."init_promise.txt";
    $init_promise = $dropbox_utility_object->file_get_utf8($init_promise);

    $completed = $update_object->status()."/"."completed.txt";
    $completed = $dropbox_utility_object->file_get_utf8($completed);




    $result = "standby";

    if ($diff !== "") {

      if ($proc_promise == "open") {

        $result = $update_object->process($update_object, $dropbox_utility_object, $diff, $time_i, $completed);
      } else {
        $result = "processing";
      }

    } elseif ($webhook == "pending") {

      $result = "initialised";

      $initialise = $update_object->initialise($update_object, $dropbox_utility_object);
      $initialise_json = json_encode($initialise, JSON_PRETTY_PRINT);

      $diff_path = $update_object->status()."/"."diff.txt";
      file_put_contents(
        $diff_path,
        $initialise_json
      );

    } elseif ($init_promise == "closed") {

      $result = "initialising";

    }

    if ($result !== "standby") {
      $status_keys = array(
        "complete" => "prcd",
        "clipping" => "rest",
        "processing" => "prc",
        "initialised" => "strtd",
        "initialising" => "strt",
        "standby" => "null",
      );

      $daystamp = date('Y-m-d', time());
      $log_path = $update_object->status()."/../logs/"."log-".$daystamp.".txt";

      $timestamp = date('H:i:s', time());
      $msg = $timestamp." - ".$status_keys[$result];
      file_put_contents(
        $log_path,
        $msg.PHP_EOL,
        FILE_APPEND
      );
    }
    return $result;

    // return $initialise;

    // $diff = $dropbox_utility_object->file_get_utf8("webhook.txt");
    // // $diff = json_decode($diff, true);

  }

  public function schedule(){

      // $t = time();
      // $timestamp = date('Y-m-d', $t)."T".date('H:i:s', $t)."Z";
      // echo $timestamp;
      // exit;

      $update_object = new update;
      $dropbox_utility_object = new dropbox_utility;

      $result = $update_object->dropbox_state_level($update_object, $dropbox_utility_object);
      // $result = $update_object->dropbox_state_level_2($update_object, $dropbox_utility_object);

      dd($result);


  }

  public function initialise($update_object, $dropbox_utility_object){
    $init_promise_path = $update_object->status()."/"."init_promise.txt";
    file_put_contents(
      $init_promise_path,
      "closed"
    );

    // $dropbox_utility_object = new dropbox_utility;
    $completed_path = $update_object->status()."/"."completed.txt";
    $completed = $dropbox_utility_object->file_get_utf8($completed_path);
    $completed = json_decode($completed, true);


    // $dropbox_state_level = $update_object->dropbox_state_level_2($update_object, $dropbox_utility_object);
    $dropbox_state_level = $update_object->dropbox_state_level($update_object, $dropbox_utility_object);

    $result["remove"] = array_diff_assoc($completed, $dropbox_state_level);
    $result["add"] = array_diff_assoc($dropbox_state_level, $completed);



    $init_promise_path = $update_object->status()."/"."init_promise.txt";
    file_put_contents(
      $init_promise_path,
      "open"
    );

    return $result;
  }

  public function dropbox_state_level_1($update_object, $dropbox_utility_object){

    $path = "";

    $result = $update_object->dropbox_state_level_1_helper($path, "", $update_object, $dropbox_utility_object);


    return $result;
  }

  public function dropbox_state_level_1_helper($path, $called, $update_object, $dropbox_utility_object){

    $result = $dropbox_utility_object->dropbox_get_request($path, $update_object, "files/list_folder");


    if (isset($result["entries"])) {
      $result = $result["entries"];

      $called = "";

      if (isset($result)) {
        foreach ($result as $key => $entry) {

          if ($entry['.tag'] == "folder") {
            $sub_result = $update_object->dropbox_state_level_1_helper($entry['path_display'], $called, $update_object, $dropbox_utility_object);
            $result[$key]["child_content"] = $sub_result;
          } else {
            $result[$key]["child_content"] = "";
          }
        }
      }
    }
    return $result;
  }

  public function dropbox_state_level_2($update_object, $dropbox_utility_object){

    $dropbox_state_level_1 = $update_object->dropbox_state_level_1($update_object, $dropbox_utility_object);

    $result = $update_object->dropbox_state_level_2_helper($dropbox_state_level_1, $update_object);

    return $result;
  }

  public function dropbox_state_level_2_helper($dropbox_state_level_1, $update_object){
    $result = array();
    if (is_array($dropbox_state_level_1)) {
      foreach ($dropbox_state_level_1 as $key => $value) {
        if (isset($value[".tag"]) and isset($value['path_display'])) {
          $name = $value["path_display"];
          // $name = str_replace("\\", "", $name);
          if ($value[".tag"] == "folder") {
            $result[$name] = 0;
            $result = array_merge($result, $update_object->dropbox_state_level_2_helper($value["child_content"], $update_object));
          } else {
            $result[$name] = $value["server_modified"];
          }
        }
      }
    }
    return $result;
  }

  public function process($update_object, $dropbox_utility_object, $diff, $time_i, $completed){
    $proc_promise_path = $update_object->status()."/"."proc_promise.txt";
    file_put_contents(
      $proc_promise_path,
      "closed"
    );

    // file_put_contents(
    //   "completed.txt",
    //   $initialise_json
    // );
    // file_put_contents("diff.txt", "");

    $pub_store = storage_path()."/app/public/";
    // $files = scandir($pub_store);

    $diff = json_decode($diff, true);
    $completed = json_decode($completed, true);
    // dd($diff);

    $result = "complete";

    $report_object = new report;
    $repo_path = $report_object->repo_path();


    foreach ($diff["remove"] as $key => $value) {

      $file_path = $repo_path.$key;

      if (file_exists($file_path)) {
        if ($value !== 0) {
          // exec( "rm $file_path");
          unlink($file_path);
        } else {
          // exec( "rm -r -f $file_path");
          $dropbox_utility_object->rrmdir($dropbox_utility_object, $file_path);
        }
      }

      $completed = $update_object->update_complete_status(
        $update_object,
        $completed,
        $diff,
        $key,
        "remove"
      );

      $diff = $update_object->update_diff_status(
        $update_object,
        $diff,
        $key,
        "remove"
      );

    }

    foreach ($diff["add"] as $key => $value) {

      $file_path = $repo_path.$key;
      // echo $file_path."<br>";

      if ($value !== 0) {

        $link_util = $dropbox_utility_object->dropbox_temp_link($key, $dropbox_utility_object);

        file_put_contents($file_path, fopen($link_util["link"], 'r'));

        // $file_content = file_get_contents($file_content);

      } else {
        mkdir($file_path);
      }

      $completed = $update_object->update_complete_status(
        $update_object,
        $completed,
        $diff,
        $key,
        "add"
      );

      $diff = $update_object->update_diff_status(
        $update_object,
        $diff,
        $key,
        "add"
      );

      $time_f = strtotime("now");
      $time_dif = $time_f-$time_i;
      if ($time_dif > 80) {
        $result = "clipping";
        break;
      }

    }

    $proc_promise_path = $update_object->status()."/"."proc_promise.txt";
    file_put_contents(
      $proc_promise_path,
      "open"
    );

    if ($result == "complete") {
      $diff_path = $update_object->status()."/"."diff.txt";
      file_put_contents(
        $diff_path,
        ""
      );
    }

    return $result;


  }

  public function update_complete_status($update_object, $completed, $diff, $key, $action){

    if ($action == "add") {
      $completed[$key] = $diff[$action][$key];
    } elseif ($action == "remove") {
      unset($completed[$key]);
    }

    $completed_json = json_encode($completed, JSON_PRETTY_PRINT);
    $completed_path = $update_object->status()."/"."completed.txt";
    file_put_contents(
      $completed_path,
      $completed_json
    );

    return $completed;

  }

  public function update_diff_status($update_object, $diff, $key, $action){

    unset($diff[$action][$key]);
    $diff_json = json_encode($diff, JSON_PRETTY_PRINT);

    $diff_path = $update_object->status()."/"."diff.txt";
    file_put_contents(
      $diff_path,
      $diff_json
    );
    return $diff;

  }

  public function dropbox_state_level($update_object, $dropbox_utility_object){

    $path = "";

    $result = $update_object->dropbox_state_level_helper($path, $update_object, $dropbox_utility_object);


    return $result;
  }

  public function dropbox_state_level_helper($path, $update_object, $dropbox_utility_object){

    $list = $dropbox_utility_object->dropbox_get_request($path, $update_object, "files/list_folder");


    if (isset($list["entries"])) {
      $list = $list["entries"];

      if (isset($list)) {
        foreach ($list as $key => $value) {

          $name = $value["path_display"];

          if ($value[".tag"] == "folder") {
            // code...
            $result[$name] = 0;
            $result = array_merge($result, $update_object->dropbox_state_level_helper($value['path_display'], $update_object, $dropbox_utility_object));
          } else {
            $result[$name] = $value["server_modified"];
          }

        }
      }
    }
    return $result;
  }

}
