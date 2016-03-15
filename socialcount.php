<?php

/*

Plugin Name: SocCount
Plugin URI: http://lukejournal.co.uk/
Description: SocCount is very simple: It exposes a custom field on any post or page, which is kept up-to-date with the number of Facebook and Twitter shares for that page (by URI).
Version: 0.1
Author: Luke Cohen
Author URI: http://lukejournal.co.uk/
License: GPLv2 or later

*/


define('SOCIALCOUNT_CHECK_TTL', 1800000);
define('LAST_SOCIALCOUNT_CHECK', 0);


function updateSocialShareCount($uriToCheck = "", $wpIDToCheck = 0, $cfTwitterCountFieldname = "SHARECOUNT_TWITTER", $cfFacebookCountFieldname = "SHARECOUNT_FACEBOOK", $cfAggregateCountFieldname = "SHARECOUNT_AGGREGATE") {


  /*

  This function checks the social counts for any given URL,
  outputting an array with three values:

  Facebook Shares Total
  Twitter Shares Total
  Aggregate Shares Total

  The function can take either an absolute URL or a WordPress PostID as input.

  The cf% variables tell the function which custom fields to use to store the
  various counts. If they're aren't defined, they're set to generic names.

  - Luke Cohen, Sep 2015 (ohmyglob.co.uk/blog)

  */

  global $wp;


  if (strlen($uriToCheck) == 0 && $wpIDToCheck == 0) {

    /* we have neither a URL or a WPID, so we're going to have to find something to query by... */
    $uriToCheck = home_url(add_query_arg(array(),$wp->request));

  }


  $msSinceLastCheck = (time()-LAST_SOCIALCOUNT_CHECK);

  if ($msSinceLastCheck == SOCIALCOUNT_CHECK_TTL || $msSinceLastCheck > SOCIALCOUNT_CHECK_TTL) {

    /* cache has expired, let's re-check */


    $aggregateCount = 0;
    $twitterCount = 0;
    $facebookCount = 0;


    /* if a WP ID was passed, let's get the URI */
    if ($wpIDToCheck > 0 && strlen($uriToCheck)==0) {
      $uriToCheck = get_permalink($wpIDToCheck);
    }


    /* if an absolute URI was passed, let's get the WP ID */
    if (strlen($uriToCheck) > 0) {
      $wpIDToCheck = url_to_postid($uriToCheck);
    }


    $countsArray = Array();

    /* 1 - get twitter counts for this URL */
    $json = file_get_contents("http://urls.api.twitter.com/1/urls/count.json?url=" . $uriToCheck);
    $json = json_decode($json, true);

    if ($json["count"]) {
      $twitterCount = intval($json["count"]);
    } else {
      $twitterCount = 0;
    }


    $countsArray["twitterCount"] = $twitterCount;


    /* 2 - get facebook counts for this URL */
    $json_string = file_get_contents("http://graph.facebook.com/?ids=" . $uriToCheck);
    $json = json_decode($json_string, true);

    if ($json[$uriToCheck]['shares']) {
      $facebookCount = intval($json[$uriToCheck]['shares']);
    } else {
      $facebookCount = 0;
    }

    $countsArray["facebookCount"] = $facebookCount;


    /* calculate aggregate */
    $countsAggregate = $twitterCount + $facebookCount;

    $countsArray["aggregateCount"] = $countsAggregate;


    /* update WP custom fields with the totals we've got */
    update_post_meta($wpIDToCheck, $cfTwitterCountFieldname, $countsArray["twitterCount"]);
    update_post_meta($wpIDToCheck, $cfFacebookCountFieldname, $countsArray["facebookCount"]);
    update_post_meta($wpIDToCheck, $cfAggregateCountFieldname, $countsArray["aggregateCount"]);


    /* reset cache timer */
    //define('LAST_SOCIALCOUNT_CHECK', 0);


    /* send counts array back to whatever called the function */
    return $countsArray;
    //echo "social counts output";
    //print_r($countsArray);


  } else {

    /* nothing to see here - we already have a cache of what we're looking for */

    return true;

  }


}

/* Hook it into WordPress */
add_action('template_redirect', 'updateSocialShareCount');


?>
