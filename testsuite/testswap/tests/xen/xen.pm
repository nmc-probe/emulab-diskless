#!/usr/bin/perl
use SemiModern::Perl;
use TestBed::TestSuite;
use Test::More;

sub build_ns(@){
my $data =<<'EOF';
# Generated by NetlabClient

set ns [new Simulator]
source tb_compat.tcl

# Nodes
set a1 [$ns node]
set a2 [$ns node]
set a3 [$ns node]
set b1 [$ns node]
set b2 [$ns node]
set b3 [$ns node]

tb-fix-node $a1 @physical_host1@
tb-fix-node $a2 @physical_host1@
tb-fix-node $a3 @physical_host1@

tb-fix-node $b1 @physical_host2@
tb-fix-node $b2 @physical_host2@
tb-fix-node $b3 @physical_host2@

tb-set-node-failure-action $a1 "nonfatal"
tb-set-node-failure-action $a2 "nonfatal"
tb-set-node-failure-action $a3 "nonfatal"
tb-set-node-failure-action $b1 "nonfatal"
tb-set-node-failure-action $b2 "nonfatal"
tb-set-node-failure-action $b3 "nonfatal"

tb-set-node-os $a1 FC8-XEN
tb-set-node-os $a2 FC8-XEN
tb-set-node-os $a3 FC8-XEN
tb-set-node-os $b1 FC8-XEN
tb-set-node-os $b2 FC8-XEN
tb-set-node-os $b3 FC8-XEN

tb-set-hardware $a1 pcvm
tb-set-hardware $a2 pcvm
tb-set-hardware $a3 pcvm
tb-set-hardware $b1 pcvm
tb-set-hardware $b2 pcvm
tb-set-hardware $b3 pcvm

tb-set-vlink-emulation "vlan"

# Links
set link0 [$ns duplex-link $a1 $a3 100000.0kb 0.0ms DropTail]
set link1 [$ns duplex-link $a3 $a2 100000.0kb 0.0ms DropTail]
set link2 [$ns duplex-link $b1 $b3 100000.0kb 0.0ms DropTail]
set link3 [$ns duplex-link $b3 $b2 100000.0kb 0.0ms DropTail]
set link4 [$ns duplex-link $a1 $b1 100000.0kb 0.0ms DropTail]
set link5 [$ns duplex-link $a2 $b2 100000.0kb 0.0ms DropTail]

$ns rtproto Static
$ns run

# NetlabClient generated file ends here.
# Finished at: 6/24/09 2:03 PM


EOF

  my ($node1, $node2) = get_free_node_names(2, 'type' => 'pc3000');
  return concretize($data, 'physical_host1' => $node1, 'physical_host2' => $node2);
}

sub testbody($){
  my (@nodes) = @_;
  return sub{
    my ($e) = @_;
    $e->wait_for_nodes_to_activate(60 * 30, @nodes); #thirty minute timeout
    # ok($e->traceroute('a1', 'b1', qw(b1-link4)), 'traceroute between a1 and b1');
    # ok($e->traceroute('a1', 'b2', qw(b1-link4 b3-link2 b2-link3)), 'traceroute between a1 and b2');
    ok($e->cartesian_ping(), 'ping all nodes');
    # ok($e->ping_from_to('a1', 'b1'), 'ping from a1 to b1');
    # ok($e->ping_from_to('a1', 'b2'), 'ping from a1 to b2');
  }
}

sub uniq(@){
    my @args = @_;
    my %x;
    for (@args){
        $x{$_} = 1;
    }
    return keys %x;
}

sub allHosts($){
    my ($nodes) = @_;
    my %all;
    foreach (keys %$nodes){
        my $a = $_;
        $all{$a} = 1;
    }
        # sayd(\%all);
    foreach (values %$nodes){
        my $a = $_;
        # sayd($a);
        foreach (@$a){
            $all{$_} = 1;
        }
        # $all{@$a} = @$a;
        # sayd(\%all);
    }
    return keys %all;
}

sub make_topology($){
    my ($topology) = @_;
    sub getHosts($){
        my ($nodes) = @_;
        my $hosts = {};
        foreach (allHosts($nodes)){
            if ($_ =~ m/(\w+)\d+/){
                    my $name = $1;
                    $hosts->{$_} = "physical_host_$name";
            }
        }
        return $hosts;
    }

    sub doVnode($$){
        my ($vnode, $host) = @_;
        my $data = "set $vnode [\$ns node]\n";
        $data .= "tb-fix-node \$$vnode \@$host\@\n";
        $data .= "tb-set-node-failure-action \$$vnode \"nonfatal\"\n";
        $data .= "tb-set-node-os \$$vnode FC8-XEN\n";
        $data .= "tb-set-hardware \$$vnode pcvm\n";

        return $data;
    }

    my $hosts = getHosts($topology);
    my $data = <<'EOF';
set ns [new Simulator]
source tb_compat.tcl

EOF
    foreach my $key (keys %$hosts){
        my $value = $hosts->{$key};

        my $vnode = $key;
        my $physical = $value;
        $data .= doVnode($vnode, $physical);
        # $data .= "set $key \@$value\@\n";
    }

    my $link = 0;
    foreach my $key (keys %$topology){
        my $from = $key;
        my $to = $topology->{$from};
        foreach (@$to){
                my $line = "set link$link [\$ns duplex-link \$$from \$$_ 100000.0kb 0.0ms DropTail]\n";
                $data .= $line;
                $link = $link + 1;
        }
    }

    $data .= <<'EOF';

tb-set-vlink-emulation "vlan"
$ns rtproto Static
$ns run
EOF

    my $num_nodes = scalar uniq(values %$hosts);
    return sub{
        my @names = get_free_node_names($num_nodes, 'type' => 'pc3000');
        my %all;
        @all{uniq(values %$hosts)} = @names;
        return concretize($data, %all);
    }
}

# rege(e('xentestingk'), \&build_ns, \&testbody, 1, "testing more xen stuff");

my $nodes = {
  "a1" => ["a3", "b1"],
  "a2" => ["b2"],
  "a3" => ["a2"],
  "b1" => ["b3"],
  "b3" => ["b2"],
};

my $foo = make_topology($nodes);
# print &$foo() . "\n";

rege(e('xentest5'), make_topology($nodes), &testbody(allHosts($nodes)), 1, "generate xen testing stuff");

1;

