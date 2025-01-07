<?php

error_reporting(E_ERROR);

require 'vendor/autoload.php';

use Dakshhmehta\DcTextract\AwsTextract;

function extractVesselSailedTable($table)
{
    $headings = [];
    $data = [];
    foreach ($table as $rowIndex => $row) {
        if ($rowIndex < 2) {
            continue;
        }

        if ($rowIndex == 2) {
            $headings = array_splice($row, 0, 9);
            continue;
        }

        // exit(var_dump($row));

        if(! empty($row[1])){
            $data[count($data)][] = array_splice($row, 0, 9);
        }

    }

    var_dump($data);
}

function search_table($needle, $haystack) {
    // Loop through each element in the array
    foreach ($haystack as $value) {
        // Check if the current value is an array
        if (is_array($value)) {
            // Recursively call search_table on the sub-array
            if (search_table($needle, $value)) {
                return true; // If found in the sub-array, return true
            }
        } else {
            // If the current value matches the needle, return true
            if ($value === $needle) {
                return true;
            }
        }
    }
    return false; // Return false if the needle is not found
}


// Handling file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $file = $_FILES['pdf_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        // AWS S3 details
        $keyName = 'uploads/' . basename($file['name']); // Unique key for S3 object

        $credentials = [
            'key'    => '',
            'secret' => '',
        ];

        $textract = new AwsTextract($credentials);

        // Upload the file to S3
        $s3Url = $textract->uploadFileToS3($file, $keyName);
        // echo "File uploaded to S3: $s3Url\n";

        // Analyze the uploaded PDF
        $tables = $textract->analyzePDF($keyName);

        // Display the tables
        foreach ($tables as $tableIndex => $table) {
            if(search_table('SAILED IN LAST 24', $table)){
                extractVesselSailedTable($table);
            }

            echo "<table border='1' cellpadding='5'><thead><tr><th>".$tableIndex."</th></tr></thead>";

            foreach ($table as $rowIndex => $row) {
                echo '<tr>';

                foreach ($row as $col) {
                    echo '<td>' . $col . '</td>';
                }

                echo '</tr>';
            }
            echo "</table>";
        }
    } else {
        echo "File upload error: " . $file['error'];
    }
}
?>

<!-- HTML Form for File Upload -->
<!DOCTYPE html>
<html>

<head>
    <title>Upload PDF for Table Analysis</title>
</head>

<body>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="pdf_file">Upload PDF:</label>
        <input type="file" name="pdf_file" id="pdf_file" required>
        <button type="submit">Analyze PDF</button>
    </form>
</body>

</html>