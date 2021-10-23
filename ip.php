<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
  <meta content="" name="description">
  <meta content="" name="author">
  <link href="" rel="icon">
  <title>IP to Range</title>
  <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" rel="stylesheet">
</head>
<?php
require_once 'vendor/autoload.php';
use GeoIp2\Database\Reader;
$reader = new Reader('/data/project/huji/public_html/GeoLite2/GeoLite2-ASN.mmdb');
?>
<body dir="ltr" style="direction:ltr">
  <div id="wrapper">
    <div id="page-content-wrapper">
      <nav class="navbar navbar-expand-lg navbar-dark bg-secondary border-bottom">
        <div class="container">
          <a class="navbar-brand" href="./">Huji's Tools</a>
          <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
              <li class="nav-item active">
                <a class="nav-link disabled" href="#">&rarr; IP to Range</a>
              </li>
            </ul>
          </div>
        </div>
      </nav>
      <div class="container">
        <h1>IP to Range</h1>
        <p>Use this tool to convert a list of IP addresses to a shorter list of CIDRs.</p>
        <p>
          The tool uses MaxMind's data about Autonomous Systems (ASNs) to map IPs to their correspondig CIDRs.
          If two or more of the IP addresses you provided are from the same ASN range, then the CIDR is returned.
          If any of the IPs you provided is unique to its ASN range, then the IP itself is returned.
        </p>
        <hr/>
        <?php if ( $_POST["iplist"] !== null ): ?>
        <p>Results for your last query:</p>
        <?php
        $cache = array();
        function update_cache( $ip, $cidr, $asn, &$cache ) {
          if ( array_key_exists( $cidr, $cache ) ) {
            $cache[$cidr]["counter"] += 1;
          } else {
            $cache[$cidr] = array(
              "first_ip" => $ip,
              "counter" => 1,
              "asn" => $asn
            );
          }
        }

        $lines = explode( "\n", $_POST["iplist"] );
        $response = array();
        foreach ( $lines as $line ) {
          $line = trim( $line );
          if ( $line === '' ) {
            continue;
          }
          try {
            $record = $reader->asn( $line );
            $network = $record->network;
            $asn = $record->autonomousSystemOrganization;
          } catch (GeoIp2\Exception\AddressNotFoundException $e) {
            $network = $line;
            $asn = 'Invalid IP';
          }
          update_cache( $line, $network, $asn, $cache);
        }

        foreach ( $cache as $cidr => $details ) {
          if ( $details["counter"] == 1 ) {
            echo "* " . $details["first_ip"] . " (" . $details["asn"] . ")<br>";
          } else {
            echo "* " . $cidr . " (" . $details["asn"] . ")<br>";
          }
        }
        ?>
        <hr/>
        <?php endif; ?>
        <form action="ip.php" method="post">
          <div class="form-group">
            <label for="iplist">Enter list of IP addresses:</label>
            <textarea class="form-control" name="iplist" id="iplist" aria-describedby="iplistHelp" placeholder="Enter one IP address per line"><?php echo $_POST["iplist"]; ?></textarea>
            <small id="iplistHelp" class="form-text text-muted">Supports IPv4 and IPv6 addresses.</small>
          </div>
          <button type="submit" class="btn btn-primary">Submit</button>
        </form>
      </div>
    </div>
  </div>
</body>

</html>