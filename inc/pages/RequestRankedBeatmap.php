<?php

class RequestRankedBeatmap {
	const PageID = 31;
	const URL = 'RequestRankedBeatmap';
	const Title = 'Ripple - Request Beatmap Ranking';
	const LoggedIn = true;
	public $error_messages = [];
	public $mh_GET = [];
	public $mh_POST = ["url"];

	public function P() {
		startSessionIfNotStarted();
		$hasSentRequest = $GLOBALS["db"]->fetch("SELECT * FROM rank_requests WHERE time > ? AND userid = ? LIMIT 1", [time()-(24*3600), $_SESSION["userid"]]);
		$rankRequests = $GLOBALS["db"]->fetchAll("SELECT * FROM rank_requests WHERE time > ? LIMIT 10", [time()-(24*3600)]);
		echo '
		<div id="content">
			<div align="center">
				<h1><i class="fa fa-music"></i> Request beatmap ranking</h1>
				<h4>Here you can send a request to rank an unranked beatmap on ripple.</h4>';
				if ($hasSentRequest) {
					if (!isset($_GET["s"]))
						echo '<div class="alert alert-warning" role="alert"><i class="fa fa-warning"></i>	You can send only <b>1 rank request</b> every 24 hours. <b>Please come back tomorrow.</b></div>';
					return;
				}
				if (count($rankRequests) >= 10) {
					echo '<div class="alert alert-warning" role="alert"><i class="fa fa-warning"></i>	A maximum of <b>10 rank requests</b> can be sent every <b>24 hours</b>. No more requests can be submitted for now. <b>Please come back later.</b></div>';
					return;
				}
				echo '<hr>
				<h2 style="display: inline;">'.count($rankRequests).'</h2><h3 style="display: inline;">/10</h3><br><h4>requests submitted</h4><h6>in the past 24 hours</h6>
				<hr>
				<div class="alert alert-warning" role="alert"><i class="fa fa-warning"></i>	Every user can send <b>1 rank request every 24 hours</b>, and a maximum of <b>10 beatmaps</b> can be requested <b>every 24 hours</b> by all users. <b>Remember that troll or invalid maps will still count as valid rank requests, so request only beatmaps that you <u>really</u> want to see ranked, since the number of daily rank requests is limited.</b></div>
				<b>Beatmap/Beatmap set link</b><br>
				<form action="submit.php" method="POST">
					<input name="action" value="RequestRankedBeatmap" hidden>
					<div class="input-group">
						<input type="text" name="url" class="form-control" placeholder="http://osu.ppy.sh/s/xxxxx">
						<span class="input-group-btn">
							<button class="btn btn-success" type="submit"><i class="fa fa-check" aria-hidden="true"></i>	Submit</button>
						</span>
					</div>
				</form>
			</div>
		</div>';
	}

	public function D() {
		startSessionIfNotStarted();
		$d = $this->DoGetData();
		if (isset($d["error"])) {
			addError($d["error"]);
			redirect("index.php?p=31");
		} else {
			// No errors, run botnet to add the new IP address
			addSuccess($d["success"]);
			redirect("index.php?p=31&s=1");
		}
	}

	public function DoGetData() {
		try {
			// Make sure the user has not submitted another beatmap
			$hasSentRequest = $GLOBALS["db"]->fetch("SELECT * FROM rank_requests WHERE time > ? AND userid = ? LIMIT 1", [time()-(24*3600), $_SESSION["userid"]]);
			if ($hasSentRequest) {
				throw new Exception("You've already sent a rank request in the past 24 hours.");
			}

			// Make sure < 10 rank requests have been submitted in the past 24 hours
			$rankRequests = $GLOBALS["db"]->fetchAll("SELECT * FROM rank_requests WHERE time > ? LIMIT 10", [time()-(24*3600)]);
			if (count($rankRequests) >= 10) {
				throw new Exception("A maximum of <b>10 rank requests</b> can be sent every <b>24 hours</b>. No more requests can be submitted for now.");
			}

			// Make sure the URL is valid
			$matches = [];
			if (!preg_match("/https?:\\/\\/(?:osu|new)\\.ppy\\.sh\\/(s|b)\\/(\\d+)/i", $_POST["url"], $matches)) {
				throw new Exception("Beatmap URL is not an osu.ppy.sh or new.ppy.sh URL.");
			}

			// Make sure the beatmap is not already ranked
			$criteria = $matches[1] == "b" ? "beatmap_id" : "beatmapset_id";
			$ranked = $GLOBALS["db"]->fetch("SELECT id FROM beatmaps WHERE ".$criteria." = ? AND ranked >= 2 LIMIT 1", [$matches[2]]);
			if ($ranked) {
				throw new Exception("That beatmap is already ranked.");
			}

			// Everything seems fine, add rank request in db
			$GLOBALS["db"]->execute("INSERT INTO rank_requests (id, userid, bid, type, time) VALUES (NULL, ?, ?, ?, ?)", [$_SESSION["userid"], $matches[2], $matches[1], time()]);

			// Send schiavo message
			Schiavo::Bunk($_SESSION["username"]." has sent a rank request for beatmap ".$_POST["url"]);

			// Set success message
			$ret["success"] = "Your beatmap ranking request has been submitted successfully! Our Community Managers will check your request and eventually rank it.";
		} catch (Exception $e) {
			$ret["error"] = $e->getMessage();
		}

		return $ret;
	}
}