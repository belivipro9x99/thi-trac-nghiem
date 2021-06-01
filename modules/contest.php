<?php
	//? |-----------------------------------------------------------------------------------------------|
	//? |  /modules/contest.php                                                                         |
	//? |                                                                                               |
	//? |  Copyright (c) 2018-2021 Belikhun. All right reserved                                         |
	//? |  Licensed under the MIT License. See LICENSE in the project root for license information.     |
	//? |-----------------------------------------------------------------------------------------------|

	require_once $_SERVER["DOCUMENT_ROOT"] ."/libs/belibrary.php";
	require_once $_SERVER["DOCUMENT_ROOT"] ."/modules/config.php";

	function updateSubmissions() {
		require_once $_SERVER["DOCUMENT_ROOT"] ."/modules/logParser.php";
		require_once $_SERVER["DOCUMENT_ROOT"] ."/modules/submissions.php";

		//? PARSE LOG FILES
		$logDir = glob(getConfig("folders.submitLogs") ."/*.log");

		foreach ($logDir as $log) {
			$data = ((new logParser($log, LOGPARSER_MODE_FULL)) -> parse());
			$id = $data["header"]["problem"];

			$sub = new Submissions($data["header"]["user"]);

			if ($sub -> exist($id) && $saved = $sub -> getData($id))
				if ($saved["header"]["point"] > $data["header"]["point"]) {
					unlink($log);
					continue;
				}

			// GET PROBLEM DETAIL
			$data["header"]["problemName"] = null;
			$data["header"]["problemPoint"] = null;
			$problemData = problemGet($id, $_SESSION["id"] === "admin");
								
			if ($problemData !== PROBLEM_ERROR_IDREJECT && $problemData !== PROBLEM_ERROR_DISABLED) {
				$data["header"]["problemName"] = $problemData["name"];
				$data["header"]["problemPoint"] = $problemData["point"];
				
				if ($data["header"]["problemPoint"] <= $data["header"]["point"])
					$data["header"]["status"] = "correct";
			}

			$sub -> saveData($id, $data);
			$sub -> saveLog($id, $log);
		}
	}

    define("CONTEST_STARTED", 1);
	define("CONTEST_NOTENDED", 2);
	define("CONTEST_ENDED", 3);

	function problemTimeRequire(String $id, Array $req = Array(
		CONTEST_STARTED,
		CONTEST_NOTENDED
	), $justReturn = true, $instantDeath = false, $resCode = 403) {
        global $problemList;

        if (!problemExist($id))
            return false;
        
        $problem = problemGet($id);
        $duringTime = $problem["time"]["during"];
        
		if ($duringTime <= 0)
			return true;

		// Admin can bypass this check
		if ($_SESSION["username"] !== null && $_SESSION["id"] === "admin")
			return true;

		$beginTime = $problem["time"]["begin"];
		$offsetTime = $problem["time"]["offset"];
		$t = $beginTime - microtime(true) + ($duringTime);

		foreach ($req as $key => $value) {
			$returnCode = null;
			$message = null;

			switch($value) {
				case CONTEST_STARTED:
					if ($t > $duringTime) {
						$returnCode = 103;
						$message = "Kì thi \"$id\" chưa bắt đầu";
					}
					break;

				case CONTEST_NOTENDED:
					if ($t < -$offsetTime && $duringTime !== 0) {
						$returnCode = 104;
						$message = "Kì thi \"$id\" đã kết thúc";
					}
					break;

				case CONTEST_ENDED:
					if ($t > -$offsetTime && $duringTime !== 0) {
						$returnCode = 105;
						$message = "Kì thi \"$id\" chưa kết thúc";
					}
					break;

				default:
					trigger_error("Unknown case: ". $value, E_USER_ERROR);
					break;
			}

			if ($returnCode !== null && $message !== null) {
				if ($justReturn === true)
					return $returnCode;

				//* Got NOTICE on Codefactor for no reason:
				//* if ($useDie === true)
				//* 	(http_response_code($resCode) && die());

				if ($instantDeath === true) {
					http_response_code($resCode);
					die();
                }

				stop($returnCode, $message, $resCode);
			}
		}

		return true;
	}