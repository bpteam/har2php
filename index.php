<form method="post" action="" enctype="multipart/form-data">
<input type="file" name="upfile">
<input type="submit" value="Send" name="submit1">
</form>
<br><br><br>

<?php

if (isset($_POST["submit1"])) {
    $uploadfile = $_FILES["upfile"]["tmp_name"];
    $data = file_get_contents($uploadfile);

    $data = json_decode($data, false, 1024000);

    unlink($uploadfile);
?><pre><?
    echo implode("\n", parseData($data));
?></pre><?
}



function parseData($data)
{
    $lines = array();

    foreach ($data->log->pages as $page) {
        $exclude_url = array();
        foreach ($data->log->entries as $entrie) {
            if ($entrie->pageref != $page->id) continue;
            if (!empty($exclude_url[$entrie->request->url])) continue;

            if (false and preg_match('/\d+-\d+-\d+T\d+:\d+:\d+\.(\d+)/', $entrie->startedDateTime, $m)) {
                $id = strftime('%Y%m%d%H%M%S', strtotime($entrie->startedDateTime)) . $m[1];
            } else {
                $id = $entrie->startedDateTime;
            }
            $newQuery[] = '$cookie_file = __DIR__ . \'/cookie.txt\';';
            $newQuery[] = '';

            $headers = array();

            foreach ($entrie->request->headers as $header) {
                $headers[$header->name] = $header->value;
            }

            $newQuery[] = '$ch = curl_init();';
            $newQuery[] = 'curl_setopt($ch, CURLOPT_URL, \'' . $entrie->request->url . '\');';
            $newQuery[] = 'curl_setopt($ch, CURLOPT_USERAGENT, \'' . $headers['User-Agent'] . '\');';
            $newQuery[] = 'curl_setopt($ch, CURLOPT_REFERER, \'' . $headers['Referer'] . '\');';
            $newQuery[] = 'curl_setopt($ch, CURLOPT_ENCODING, \'' . $headers['Accept-Encoding'] . '\');';
            $newQuery[] = 'curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);';
            $newQuery[] = 'curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);';
            $newQuery[] = '';

            unset($headers['Cookie'], $headers['User-Agent'], $headers['Referer'], $headers['Accept-Encoding']);

            $newQuery[] = '$header = [];';
            $newQuery[] = '';
            switch ($entrie->request->method) {
                case 'GET':
                    $newQuery[] = 'curl_setopt($ch, CURLOPT_POST, false);';
                    break;
                case 'POST':
                    if ($entrie->request->postData->mimeType == 'application/x-www-form-urlencoded'
                        || $entrie->request->postData->text) {
                        $newQuery[] = 'curl_setopt($ch, CURLOPT_POST, true);';
                    } else {
                        $newQuery[] = 'curl_setopt($ch, CURLOPT_POST, false);';
                    }
                    $newQuery[] = '';
                    $newQuery[] = '$fields = [];';
                    if ($entrie->request->postData->text) {
                        unset($headers['Content-Type']);
                        $newQuery[] = '$header[] = \'Content-Type: text/plain\';';
                        $text = addcslashes($entrie->request->postData->text, '"');
                        $text = preg_replace("%\r\n%", '\r\n', $text);
                        $text = preg_replace("%\n%", '\n', $text);

                        $newQuery[] = '$fields[] = "' . $text . '";';
                    } else {
                        foreach ($entrie->request->postData->params as $param) {
                            $newQuery[] = '$fields[] = \'' . $param->name . '='. $param->value . '\';';
                        }
                    }
                    $newQuery[] = '';
                    $newQuery[] = 'curl_setopt($ch, CURLOPT_POSTFIELDS, implode(\'&\', $fields));';
                    break;
                default:
                    die(print_r($entrie));
            }
            $newQuery[] = '';

            foreach ($headers as $name => $header) {
                $newQuery[] = '$header[] = \'' . $name . ': ' . $header . '\';';
            }

            $newQuery[] = '$header[] = \'Pragma: \';';
            $newQuery[] = 'curl_setopt($ch, CURLOPT_HTTPHEADER, $header);';

            if ($entrie->response->status == '302' || $entrie->response->status == '301') {
                $newQuery[] = 'curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);';
                $newQuery[] = 'curl_setopt($ch, CURLOPT_AUTOREFERER, true);';
                $exclude_url[$entrie->response->redirectURL] = true;
            } else {
                $newQuery[] = 'curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);';
                $newQuery[] = 'curl_setopt($ch, CURLOPT_AUTOREFERER, false);';
            }
            $newQuery[] = 'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);';
            $newQuery[] = 'curl_exec($ch);';
            $newQuery[] = 'curl_close($ch);';
            $newQuery[] = '';
            $lines[] = implode("\n", $newQuery);
        }
    }

    return $lines;
}
?>