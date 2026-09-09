$filenamepath = 'c:\temp\test.txt'
new-Item -ItemType file $filenamepath
$datetime = Get-Date -Format "yyyy-MM-dd-HH-mm-ss"
$text = "This is a test from $datetime"
$text | out-file $filenamepath -append
