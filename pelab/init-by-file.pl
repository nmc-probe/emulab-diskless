
#usage: init-by-file.pl proj exp input-file
#
# Input file is generated by init-elabnodes.pl using the -o option.

$proj = $ARGV[0];
$exp = $ARGV[1];
$filename = $ARGV[2];

open(FILE, "<".$filename) or die("Cannot open $filename\n");

while ($line = <FILE>)
{
    $line =~ /^10.0.0.([0-9]+) ([0-9.]+) ([0-9.]+) ([0-9.]+) [0-9.]+$/;
    print("/usr/testbed/bin/tevc -e $proj/$exp now elabc-elab-$1 modify dest=$2 bandwidth=$3\n");
    print("/usr/testbed/bin/tevc -e $proj/$exp now elabc-elab-$1 modify dest=$2 delay=$4\n");
    system("/usr/testbed/bin/tevc -e $proj/$exp now elabc-elab-$1 modify dest=$2 bandwidth=$3");
    system("/usr/testbed/bin/tevc -e $proj/$exp now elabc-elab-$1 modify dest=$2 delay=$4");
}
