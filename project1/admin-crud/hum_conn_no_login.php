<?php
    /*-----
        function: hum_conn_no_login: void -> connection
        purpose: expects nothing,
        
            has the side-effect of trying to connect to
            Humboldt's Oracle student database based on where
            the PHP file resides,
            
            and, if successful, returns the resulting connection 
            object,

            but, if NOT successful, ENDS the document and exits the
            current PHP document...! (yes, it can do that...!)

        uses: 328footer-plus-end.html
        last modified: 2025-04-06
    -----*/

    function hum_conn_no_login()
    {
        // get part of the username from where this is installed

        $os_username = substr($_SERVER["CONTEXT_PREFIX"], 2);

        // but the Oracle account you can log into using OCI is your 
        //     username plus _php

        $ora_php_username = "{$os_username}_php";

        // but, to ask to use blah_php's password to log in as blah,
        //     we need to express it in the form blah_php[blah]

        $conn_username = "{$ora_php_username}[{$os_username}]";   

        // grab the password from this user's .oraauth

        $ora_php_password =
            trim(file_get_contents("/home/{$os_username}/.oraauth"));

        // now: oci_connect expects:
        //    a username,
        //    a password,
        //    a connection string (can be null in this particular 
        //                         approach, so PHP can build it from 
        //                         environment variables),
        //    the desired character encoding (we'll use "AL32UTF8"),
        //    the desired session mode (we'll use the default, the 
        //                              PHP constant OCI_DEFAULT)

        $connectn = oci_connect($conn_username, $ora_php_password, 
                                null, "AL32UTF8", OCI_DEFAULT);

        // complain and exit at least somewhat gracefully if
        //     oci_connect fails to make a connection

        if (! $connectn)
        {
            ?>
            <p> Could not log into Oracle, sorry! </p>
            <?php
            require_once("328footer-plus-end.html");

            // exit this PHP now -- this is reasonable
            //     when you have hit an error and there is NO
            //     point in going forward
            
            exit;
        }
    
        // if reach here, I connected!

        return $connectn;
    }
?>
