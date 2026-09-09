

$filenamepath = 'c:\temp\test.txt'
if (test-path $filenamepath){
    new-Item -ItemType file $filenamepath
}

$datetime = Get-Date -Format "yyyy-MM-dd-HH-mm-ss"
$text = "This is a test from $datetime"
$text | out-file $filenamepath -append