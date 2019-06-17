<?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;
use WebPConvert\Loggers\EchoLogger;

/**
 * Class for generating a HTML report of converting an image.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Report
{
    public static function convertAndReport($source, $destination, $options)
    {
        ?>
<html>
    <head>
        <style>td {vertical-align: top} table {color: #666}</style>
        <script>
            function showOptions(elToHide) {
                document.getElementById('options').style.display='block';
                elToHide.style.display='none';
            }
        </script>
    </head>
    <body>
        <table>
            <tr><td><i>source:</i></td><td><?php echo $source ?></td></tr>
            <tr><td><i>destination:</i></td><td><?php echo $destination ?><td></tr>
        </table>
        <br>
        <?php
        try {
            $echoLogger = new EchoLogger();
            $options['log-call-arguments'] = true;
            WebPConvert::convert($source, $destination, $options, $echoLogger);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            echo '<b>' . $msg . '</b>';

            //echo '<p>Rethrowing exception for your convenience</p>';
            //throw ($e);
        }
        ?>
    </body>
    </html>
        <?php
    }
}
