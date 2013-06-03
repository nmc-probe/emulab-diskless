#!/usr/bin/ruby

def command?(name)
  `which #{name}`
  $?.success?
end

class EmulabExport
    attr_accessor :identity

    def initialize()
        @workdir = File.dirname(File.expand_path $0) 
    end

    def finalize()
        system("rm -Rf ec2-ami-tools-1.4.0.9 > /dev/null 2>&1")
        system("rm ec2-ami-tools.zip > /dev/null 2>&1")
    end

    def create_image()
        raise "Failed fetching ec2-utils" unless
            system("wget http://s3.amazonaws.com/ec2-downloads/ec2-ami-tools.zip" +
            " -O " + @workdir + "/ec2-ami-tools.zip")
        raise "Failed unzippinging ec2-utils" unless
            system("unzip " + @workdir +"/ec2-ami-tools.zip")

        $:.unshift(Dir.pwd + "/ec2-ami-tools-1.4.0.9/lib/")
        require 'ec2/platform/current'

        excludes = ['/tmp/emulab-image', '/dev', '/media', '/mnt',
            '/proc', '/sys', '/', '/proc/sys/fs/binfmt_misc', '/dev/pts',
            '/var/lib/cloud/sem', @workdir]

       
        image = EC2::Platform::Current::Image.new("/",
                        "/tmp/emulab-image",
                        fssize+800,
                        excludes,
                        [],
                        false,
                        nil,
                        true)
        image.make
    end

    def check_prereqs()
        raise "No unzip found. Please install unzip" unless
            command?("unzip")

        # Remove any previous image tries
        system("rm /tmp/emulab-image >/dev/null 2>&1");
        system("rm " + @workdir + "/* >/dev/null 2>&1");


        # TODO this probably needs to be more elaborate
        fssize = Integer(`df -PBM --total / | grep total | awk '{gsub(/M$/,"",$3);print $3}'`)
        empsize = Integer(`df -PBM --total / | grep total | awk '{gsub(/M$/,"",$4);print $4}'`)
        puts "Disk on / has " + fssize.to_s + "M of data and " +
            empsize.to_s + "M free space"
        raise "Not enough disk space to create image" if empsize < fssize * 1.7
        
    end

    def get_kernel()
        version = `uname -r`.chomp

        pkernels = []
        pkernels << "/boot/vmlinuz-" + version
        pkernels << "/boot/vmlinuz-" + version + ".img"

        pinitrd = []
        pinitrd << "/boot/initramfs-" + version + ".img"
        pinitrd << "/boot/initrd-" + version
        pinitrd << "/boot/initrd-" + version + ".img"
        pinitrd << "/boot/initrd.img-" + version
        # TODO Screw this. A few more and I might as well parse the menu.lst

        kernelfound = false
        pkernels.each do |kernel|
            if File.exists?(kernel)
                kernelfound = true
                raise "Couldn't copy kernel" unless
                    system("cp " + kernel + " " + @workdir + "/kernel")
                break
            end
        end
        raise "Couldn't find kernel" if kernelfound == false

        initrdfound = false
        pinitrd.each do |initrd|
            if File.exists?(initrd)
                initrdfound = true
                raise "Couldn't copy initrd" unless
                    system("cp " + initrd + " " + @workdir + "/initrd")
                break
            end
        end
        raise "Couldn't find initrd" if initrdfound == false

    end


    def get_bootopts()
        raise "Couldn't get bootopts" unless
            system("cat /proc/cmdline > " + @workdir + "/bootopts") 
    end

    def gen_tar()
        puts "Running:  tar -cvzf emulab.tar.gz kernel initrd" +
            " bootopts -C /tmp/ emulab-image 2>&1"
        raise "Couldn't tar" unless
            system("tar -cvzf emulab.tar.gz kernel initrd" +
            " bootopts -C /tmp/ emulab-image 2>&1")
    end

end


if __FILE__ == $0
    raise 'Must run as root' unless Process.uid == 0

    retval = 0
    begin
        ex = EmulabExport.new()
        ex.check_prereqs
        ex.get_kernel
        ex.get_bootopts
        ex.create_image
        ex.gen_tar
    rescue Exception => e
        print "Error while creating an image: \n"
        puts e.message
        puts e.backtrace.join("\n")
        retval = 1
    ensure
        ex.finalize()        
    end
    exit retval
end

