<?php

namespace Dakshhmehta\DcTextract;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\Textract\TextractClient;


class AwsTextract
{
    protected $s3Client;
    protected $textract;

    public $bucketName = 'textract-console-ap-south-1-14eb50c6-5b1c-4576-9068-d8f7acb9f41';
    public $region = 'ap-south-1';


    public function __construct($credentials)
    {
        // Initialize S3 Client
        $s3Client = new S3Client([
            'region' => $this->region, // Change to your region
            'version' => 'latest',
            'credentials' => $credentials,
        ]);

        // Initialize Textract Client
        $textract = new TextractClient([
            'region' => $this->region, // Change to your region
            'version' => 'latest',
            'credentials' => $credentials,
        ]);


        $this->textract = $textract;
        $this->s3Client = $s3Client;
    }

    public function uploadFileToS3($file, $keyName)
    {
        try {
            // Upload the file to S3
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key'    => $keyName,
                'SourceFile' => $file['tmp_name'],
                'ACL'    => 'private', // Adjust ACL as needed
            ]);

            return $result['ObjectURL'];
        } catch (AwsException $e) {
            die("Error uploading file to S3: " . $e->getMessage());
        }
    }

    public function analyzePDF($fileName)
    {
        try {
            // Start Document Analysis
            $result = $this->textract->startDocumentAnalysis([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $this->bucketName,
                        'Name'   => $fileName,
                    ],
                ],
                'FeatureTypes' => ['TABLES'],
            ]);

            $jobId = $result['JobId'];
            // echo "Job ID: $jobId\n";

            // Poll the job status
            do {
                sleep(5); // Wait for 5 seconds before polling again
                $status = $this->textract->getDocumentAnalysis(['JobId' => $jobId]);
                $jobStatus = $status['JobStatus'];
                // echo "Job Status: $jobStatus\n";
            } while ($jobStatus === 'IN_PROGRESS');

            if ($jobStatus === 'SUCCEEDED') {
                $tables = [];
                foreach ($status['Blocks'] as $block) {
                    if ($block['BlockType'] === 'TABLE') {
                        $tables[] = $this->extractTable($block, $status['Blocks']);
                    }
                }

                return $tables;
            } else {
                throw new \Exception("Job failed with status: $jobStatus");
            }
        } catch (AwsException $e) {
            echo "AWS Error: " . $e->getMessage();
        }
    }

    protected function extractTable($tableBlock, $blocks)
    {
        $rows = [];
        foreach ($tableBlock['Relationships'] as $relationship) {
            if ($relationship['Type'] === 'CHILD') {
                foreach ($relationship['Ids'] as $cellId) {
                    foreach ($blocks as $block) {
                        if ($block['Id'] === $cellId && $block['BlockType'] === 'CELL') {
                            $rowIndex = $block['RowIndex'];
                            $colIndex = $block['ColumnIndex'];
                            $text = $this->getText($block, $blocks);

                            $rows[$rowIndex][$colIndex] = $text;
                        }
                    }
                }
            }
        }

        return $rows;
    }

    protected function getText($cellBlock, $blocks)
    {
        $text = '';
        foreach ($cellBlock['Relationships'] as $relationship) {
            if ($relationship['Type'] === 'CHILD') {
                foreach ($relationship['Ids'] as $childId) {
                    foreach ($blocks as $block) {
                        if ($block['Id'] === $childId && $block['BlockType'] === 'WORD') {
                            $text .= $block['Text'] . ' ';
                        }
                    }
                }
            }
        }

        return trim($text);
    }
}
