<?php
#!/usr/bin/php
/**
* @package console
* @since 2005-04-20
* @author jonathan.gotti@free.fr
* @licence LGPL
* @changelog
*            - 2010-07-20 - allow configurable console_app::msg_confirm() possible answers
*            - 2007-05-31 - better support in display help for multiline parameter's descriptions
*                         - better consistency between args and flags help display
*            - 2007-02-02 - display_help(false) can display help without exiting the app
*            - 2007-01-26 - add static property noColorTag
*            - 2007-01-24 - new dbg(), moveUp(), moveDown() methods
*            - 2007-01-09 - new table style attribute maxwidth and some optimisation made in the styles operations
*            - 2007-01-02 - styles for methods msg_*  are no located in static porperty console_app::$dflt_styles
*            - 2006-12-06 - now console_app::read check for input strlen to avoid returning false or default value on '0' input
*            - 2006-11-27 - new styles parameter can be used for print_table (dflt row styles, width, headers, with or without lines...)
*            - 2006-10-10 - rewrite for php5
*                         - better support for history
*            - 2006-08-17 - new parameter $dfltIsYes for method msg_confirm()
*            - 2006-05-10 - progress_bar() and refresh_progress_bar() now support array(msg,tag) as $msg parameter
*            - 2006-05-04 - new parameters $styles and $return for console_app::print_table()
*            - 2006-05-02 - now read will return false on CTRL+D if property _captureEOT is false
*            - 2006-04-26 - call of console_app::read() as a static method will now autodetect and use the readline extension
*            - 2006-04-23 - now console_app::read() and console_app::msg_	read() even as a static method
*            - 2006-04-16 - now console_app::get_arg() can also retrieve unknown_args ($console_app->get_arg(0))
*            - 2006-04-15 - now auto add readline history if readline enable.
*                         - new parameter _captureEOT to capture EndOfTransmission signal (CTRL+d)
*            - 2006-04-14 - new property console_app::_lnOnRead to remove the automaticly added new line on read msg.
*                         - new methods progress_bar() and refresh_progress_bar()
*            - 2006-04-13 - now you can use "\n" in args descriptions
*                         - new method print_table() for table rendering
*            - 2006-02-08 - remove some E_NOTICE on previously added readline support
*            - 2006-02-03 - now can read multiple SHORT FLAG at once (ie: -AZEXC instead of -A -Z -E -X -C )
*            - 2006-01-30 - some visual amelioration on the display_help
*            - 2006-01-12 - new msg_read method
*            - 2005-12-29 - now use STDIN/STDOUT/STDERR and add a fancyerror parameter
* @example
* @code
# set the app
$app = new console_app();
$app->set_app_desc("This is a sample console app.");
# set parameters
$app->define_arg('name','n','','your name');

# don't forget to parse the command line to get args
$app->parse_args();

# get the value of 'name' like this
$name = $app->get_arg('name');
if( $name =='' )
  $name = console_app::read("enter your name");

# display a hello msg
console_app::msg("Hello $name",'blue');
@endcode
@todo add better support for numeric args to permit to define some help for them too and know them at all
*/


class console_app{
	/** args known by this class */
	private $known_args = array();
	/** flags known by this class */
	private $known_flags = array();
	/** unknown given args */
	private $unknown_args = array();
	/** setted args on command line */
	private $setted_args = array();
	/** setted flags on command line */
	private $setted_flags = array();
	public  $app_desc = '';
	private $required_args;
	private $required_flags;
	/** is there a readline extension available or not */
	public static $useReadline = false;
	public static $lnOnRead = true;
	public static $captureEOT = true; # exit on EndOfTransmission (CTRL+d)
	/** Manage history file Require readline extension */
	public static $historyFile = null;
	public static $historyMaxLen = 1000;
	/** permit to easily remove tags from apps when this is not comfortable to use them. */
	public static $noColorTag = false;
	/** default styles for various methods */
	public static $dflt_styles = array(
		'info'    => array('blue','::Info::',''),                 # tag / prefix / suffix
		'error'   => array('red','::ERROR::',''),                 # tag / prefix / suffix
		'confirm' => array('brown','::Confirmation::',"Yes|No"),# tag / prefix / suffix (suffix is also used as default answers yes|no )
		/** dbg method default styles */
		'dbg'     => array(
			'tag'       => 'bold|red',
			'print_func'=> 'var_export',
			'breakStr'  => 'press enter to continue.',
			'askexitStr'=> 'press enter to continue anything else to exit.',
		),
		/** see styles param of print_table method for more detail (avoid setting anything else than 'noheader','nolines','dflt') */
		'table'         => array(
			'nolines'     =>false,
			'noheader'    =>false,
			'dflt'        =>null,
			'headertag'   => 'reverse',
			'hchr'        => '~',
			'vchr'        => '|',
			#- 'pageAttrs'=> array(
			#- 'first'    => array("<<",'bold|underline'),
			#- 'prev'     => array("<",'bold|underline'),
			#- 'next'     => array(">",'bold|underline'),
			#- 'last'     => array(">>",'bold|underline'),
			#- 'pages'    => array('%page',''),
			#- 'curpage'  => array('%page',''),
			#- 'formatStr'=> "total %tot resultats:  %first %prev page %1links / %nbpages %next %last
			#- ( commande de pagination: <<, <, nbpage, >, >>. commandes de tri: nomduchamp [asc|desc] )",
			#- ),
		),
		'help' =>array(
			'msg'   => "-h or --help to display help",
			'width' => 80
		)
	);

	public function __construct($historyfile=null,$autoDetectReadline=true){
		if($autoDetectReadline)
			console_app::$useReadline = function_exists('readline');
		if($historyfile ){
			if(! console_app::$useReadline ){ # check for readline if not already done
				console_app::$useReadline = function_exists('readline');
			}
			if(! console_app::$useReadline )
				console_app::msg_error("Can't use history without readline extension.");
			elseif(is_file($historyfile))
				readline_read_history($historyfile);
		}
		# $this->workingdir   = getcwd();
		# $this->appdir       = dirname($_SERVER['PHP_SELF']);
	}
	# function set_working_dir($dir=null){
		# switch($dir){
			# case null:  $dir = $this->workingdir; break;
			# case '@pp': $dir = $this->appdir; break;
		# }
		# chdir($dir);
	# }
	public function get_arg($longname){
		# try args
		if( isset($this->setted_args[$longname]) )
			return $this->setted_args[$longname];
		if( isset($this->setted_flags[$longname]) )
			return $this->setted_flags[$longname];
		if( isset($this->unknown_args[$longname]) )
			return $this->unknown_args[$longname];
		return false;
	}
	public function get_args(){
		return array_merge($this->setted_args,$this->setted_flags,$this->unknown_args);
	}
	/**
	* define the flags the programm will wait for
	* @param string $flagname the name of the flag (so --flagname will work)
	* @param mixed  $sf       the short flag to use can be a string or an array
	*                         if an array is used then the 1st value will set flag to true and the 2d to false
	*                         if so a --no-flagname will be created too
	* @param bool  $dflt      the default value for this flag leave null if this is a required flag
	*                         (DON'T USE 'unset' ANYMORE to leave null a flag without setting him as required, SET IT TO FALSE will do the same)
	* @param string $desc      description for the help screen
	*/
	public function define_flag($flagname,$sf=null,$dflt=null,$desc='** no description available **'){
		$this->known_flags['--'.$flagname] = $flagname;
		# $this->flags[$flagname]['longname']= $name;
		$this->flags[$flagname]['desc'] = $desc;
		if($dflt==='unset') $dflt=false; # this line is just here to handle old silly 'unset' value for old prog

		# check for default value or set it as required
		if( is_null($dflt) )
			$this->required_flags[]  = $flagname;
		else
			$this->setted_flags[$flagname] = $this->flags[$flagname]['dflt'] = (bool) $dflt;

		# check short tags
		if($sf){
			if(is_array($sf))
				list($sf,$usf) = $sf;
			$this->known_flags['-'.$sf] = $flagname;
		}

		# define optionnal unflag
		if(isset($usf))
			$this->define_unflag($flagname,"no-$flagname",$usf);
	}
	/**
	* set a unflag flag for$flagname
	* @param string $flagname         flagname to set an unflag for
	* @param string $unflagname       the unflag longname
	* @param string $shortunflagname  the short unflag name
	*/
	public function define_unflag($flagname,$unflagname=null,$shortunflagname=null){
		if(! isset($this->flags[$flagname])){
			console_app::msg_error('unflag set for non existing flag: '.$flagname);
			return false;
		}
		if(! is_null($unflagname))
			$this->unflags['--'.$unflagname] = $flagname;
		if(! is_null($shortunflagname))
			$this->unflags['-'.$shortunflagname] = $flagname;
		return true;
	}
	public function set_flag($flag,$value=true){
		# try in known_flags
		if( isset($this->knwon_flags[$flag]) ){
			$this->setted_flags[$this->knwon_flags[$flag]] = $value;
			return true;
		}
		# try in unflags
		if( isset($this->unflags[$flag]) ){
			$this->setted_flags[$this->unflags[$flag]] =(bool) (! $value);
			return true;
		}
		#try as flagname
		if(isset($this->flags[$flag])){
			$this->setted_flags[$flag] = $value;
			return true;
		}
		return console_app::msg_error('Try to set an unknwon flag: '.$flag);
	}
	/**
	* set possible arg for this application
	* @param str    $longname    this is the name you will use to access this arg inside your application.
	*                            the program may so have a --longname argument
	* @param str    $shortname   set an optionnal short name for arg, so your app will take a -S arg (S for shortname :))
	* @param str    $default     optionnal default value to set this arg to, if not passed on the command line
	*														 Setting a default value (!== null) will mark this arg as optionnal else it will be a required arg
	* @param str    $desc       set the description for this argument used for the --help command
	* @param mixed  $valid_cb   You can use a callback function to check your argument at the start time
	*                           such function will receive the value given to arg by user on the command line
	*                           the callback func must return either: FALSE -> so program will display an error and exit
	*                                                                 TRUE or null -> nothing happen all is ok
	*                                                                 mixed -> the value will be replaced by the returned mixed
	* @param str    $delim      delimiter used to explode multiple value agurment
	*/
	public function define_arg($longname,$shortname=null,$default=null,$desc='** no description available **',$valid_cb=null,$delim=null){
		$this->known_args['--'.$longname] = $longname;
		$this->_args[$longname] = array('longname'=>$longname,'desc'=>$desc);
		if(! is_null($valid_cb) )
			$this->_args[$longname]['validation_callback'] = $valid_cb;
		if(! is_null($delim) )
			$this->_args[$longname]['delim'] = $delim;
		if( is_null($default) ){
			$this->required_args[] = $longname;
		}else{
			$this->_args[$longname]['dflt'] = $default;
			$this->setted_args[$longname]   = $default;
		}
		if(! is_null($shortname)){
			$this->_args[$longname]['short'] = $shortname;
			$this->known_args['-'.$shortname]= $longname;
		}
	}
	/**
	* create and print the help page
	* @param int $exitcode default is 0 this mean normal exit status.
	*                      you can pass FALSE to avoid exiting
	*/
	public function display_help($exitcode=0){
		$max_len=0;
		$appname = self::tagged_string(basename($_SERVER['SCRIPT_NAME']),'bold|blue');
		if(strlen($this->app_desc))
			fwrite(STDOUT,wordwrap("$this->app_desc",self::$dflt_styles['help']['width'])."\n");
		fwrite(STDOUT,self::$dflt_styles['help']['msg']."\n");
		$i=0;
		# Display help for args
		if( count($this->known_args)){
			ksort($this->known_args);
			$rows[$i] = "\n### OPTIONS LIST FOR $appname\n";
			foreach($this->_args as $argname=>$arg){
				$rows[++$i][0] = (isset($arg['short'])?'-'.$arg['short'].', ':'')."--$argname";
				$rows[$i][1]   = (isset($arg['dflt'])?"(Default value: '$arg[dflt]') ":'').$arg['desc']
					.((isset($arg['delim']) && strlen($arg['delim']))?" multiple values can be separated by '$arg[delim]'":'');
				if( (! is_array($this->required_args))|| (! in_array($argname,$this->required_args)) )
					$rows[$i][0] = "[".$rows[$i][0]."]";
				$max_len     = max($max_len,strlen($rows[$i][0]));
				$parsed_arg[$arg['longname']]=true;
			}
		}
		# Display help for flags
		if( isset($this->flags) && count($this->flags)){
			ksort($this->flags);
			$rows[++$i] = "\n### FLAGS / SWITCHES LIST FOR $appname\n";
			foreach($this->flags as $flagname=>$flag){
				$rows[++$i][0] = implode(', ',array_reverse(array_keys($this->known_flags,$flagname)));
				$rows[$i][1]   = (isset($flag['dflt'])?"(Default: ".($flag['dflt']?'on':'off').") ":'')."switch '".$flagname."' to on. ".$flag['desc'];
				# is optional?
				if( (! is_array($this->required_flags)) || (! in_array($flagname,$this->required_flags)) )
					$rows[$i][0] = '['.$rows[$i][0].']';
				$max_len       = max($max_len,strlen($rows[$i][0]));
				# check for unflags
				if( isset($this->unflags) && is_array($this->unflags) && $unflags = array_reverse(array_keys($this->unflags,$flagname))){
					$rows[++$i][0] = implode(', ',$unflags);
					$rows[$i][1]   = 'switch \''.$flagname.'\' to off.';
					if( (!is_array($this->required_flags)) || (! in_array($flagname,$this->required_flags)) )
						$rows[$i][0] = '['.$rows[$i][0].']';
					$max_len      = max($max_len,strlen($rows[$i][0]));
				}
			}
		}
		$max_len +=4;
		$split = self::$dflt_styles['help']['width'];
		$blank    = str_repeat(' ',$max_len);
		$desclen  = max(10,$split-$max_len); # avoid bad settings
		foreach($rows as $row){
			if(is_string($row)){fwrite(STDOUT,$row);continue;} # echo single lines
			list($col1,$col2) = $row;
			# print first col
			fwrite(STDOUT,$col1.str_repeat(' ',max(0,strlen($blank)-strlen($col1))) );
			# print 2d col
			while(strlen($col2) > $desclen){
				if( ($lnpos = strpos($col2,"\n")) !==false && $lnpos < $desclen){
					fwrite(STDOUT,substr($col2,0,$lnpos)."\n$blank");
					$col2 = substr($col2,$lnpos+1);
					continue;
				}
				$last_isspace = (bool) preg_match('!\s!',$col2[$desclen-1]);
				$next_isspace = (bool) preg_match('!\s!',$col2[$desclen]);
				$next2_isspace= (bool) (isset($col2[$desclen+1])?preg_match('!\s!',$col2[$desclen+1]):true);
				if($last_isspace){
					fwrite(STDOUT,substr($col2,0,$desclen)."\n$blank");
					$col2 = substr($col2,$desclen);
				}elseif($next_isspace){
					fwrite(STDOUT,substr($col2,0,$desclen)."\n$blank");
					$col2 = substr($col2,$desclen+1);
				}elseif($next2_isspace){
					fwrite(STDOUT,substr($col2,0,$desclen+1)."\n$blank");
					$col2 = substr($col2,$desclen+2);
				}else{
					fwrite(STDOUT,substr($col2,0,$desclen)."-\n$blank");
					$col2 = substr($col2,$desclen);
				}
			}
			fwrite(STDOUT,$col2."\n");
		}
		if($exitcode!==false)
			exit($exitcode);
	}
	/** add an header string to explain the programm behaviour, will be displayed on the help screen */
	public function set_app_desc($string){
		$this->app_desc = $string;
	}
	/**
	* parse command line parameters.
	* You must call this method after args and flags definition and before any console_app::get_arg() call
	* @param bool $dontDisplayHelp by passing this as true the app won't display the full help on invalid parameter
	*/
	public function parse_args($dontDisplayHelp=false){
		$argv = $_SERVER['argv'];
		$argc = $_SERVER['argc'];
		# exit if no args given
		if(! $argc>1)
			return false;
		# we parse each args
		for($i=1;$i<$argc;$i++){
			$arg = $argv[$i];
			if( in_array($arg,array('--help','-h')) ) # check for help
				return $this->display_help(0);

			if( substr($arg,0,1)!='-' ){ # not a flag or arg
				$this->unknown_args[] = $arg;
				continue;
			}

			if( isset($this->known_args[$arg]) ){ # Known argument so we process it
				$name = $this->known_args[$arg]; # get arg name
				# get his value
				if(! isset($argv[$i+1]) ) continue;
				if(! isset($this->_args[$name]['delim']) )# unique value entry
					$this->setted_args[$name] = isset($argv[++$i])?$argv[$i]:false;
				else # multiple value argument
					$this->setted_args[$name] = split($this->_args[$name]['delim'],$argv[++$i]);
				if(isset($this->_args[$name]['validation_callback'])){ # run optionnal validation callback
					$cb_ret = call_user_func($this->_args[$name]['validation_callback'],$this->setted_args[$name]);
					if($cb_ret===false){ # callback failed so display error message and then help
						console_app::tagged_string("** '".$arg .' '.$argv[$i]."' Invalid value given for $name **",'red|bold',1);
						if(! $dontDisplayHelp )
							return $this->display_help(-1);
						console_app::msg(self::$dflt_styles['help']['msg']);
						exit(-1);
					}elseif(! in_array($cb_ret,array(true,null),true) ){ # callback returned a value so we override user value with this one
						$this->setted_args[$name] = $cb_ret;     # get the args value
					}
				}
			}elseif( isset($this->known_flags[$arg]) ){     # known flag
				$name = $this->known_flags[$arg]; # get arg name
				$this->setted_flags[$name] = true;
			}elseif( isset($this->unflags[$arg])){ # known unflag
				$this->setted_flags[$this->unflags[$arg]] = false;
			}else{ # unknown flag or args
				$_arg = substr($arg,1);
				if(isset($this->known_flags)){
					foreach($this->known_flags as $k=>$v){
						if(! strlen($_arg) ) break;
						$k=substr($k,1);
						if( substr_count($_arg,$k) ){
							$this->setted_flags[$v] = true;
							$_arg = str_replace($k,'',$_arg);
						}
					}
				}
				if(isset($this->unflags)){
					foreach($this->unflags as $k=>$v){
						if(! strlen($_arg) ) break;
						$k=substr($k,1);
						if( substr_count($_arg,$k) ){
							$this->setted_flags[$v] = false;
							$_arg = str_replace($k,'',$_arg);
						}
					}
				}
				if( strlen($_arg) ){
					console_app::tagged_string("** undefined parameter $arg **",'red',1);
					if(! $dontDisplayHelp )
						return $this->display_help(-1);
					console_app::msg(self::$dflt_styles['help']['msg']);
					exit(-1);
				}
			}
		}
		if( is_array($this->required_args))
			foreach($this->required_args as $arg){
				if(! isset($this->setted_args[$arg]))
					console_app::msg_error("** Missing required $arg parameter (".($this->_args[$arg]['short']?'-'.$this->_args[$arg]['short'].', ':'')."--$arg)**",true);
			}
		if( is_array($this->required_flags) )
			foreach($this->required_flags as $flag){
			if(! isset($this->setted_flags[$flag]))
				console_app::msg_error("** Missing required flag: $flag ("
					.($this->flags[$flag]['short']?'-'.$this->flags[$flag]['short'].', ':'')
					."--$flag) **",true
				);
			}
	}
	/**
	* return a tagged console string, used to print color string on the command line interface.
	* @param string $string the string to display
	* @param string $tag the tag identifier to use (like color name) multiple tags can be passed separated by '|'
	* @param bool   $stdout if set to true then send string to stdout instead of return it
	*/
	public static function tagged_string($string,$tag='blink',$stdout=false){
		static $codes;

		# define some escaped commands code
		if(! isset($codes) ){
			$codes = array( # some cool stuff
				'reset'      => 0,   'bold'       => 1,
				'underline'  => 4,   'nounderline'=> 24,
				'blink'      => 5,   'reverse'    => 7,
				'normal'     => 22,  'blinkoff'   => 25,
				'reverse'    => 7,   'reverseoff' => 27,
				# some foreground colors
				'black'      => 30,  'red'        => 31,
				'green'      => 32,  'brown'      => 33,
				'blue'       => 34,  'magenta'    => 35,
				'cyan'       => 36,  'grey'       => 37,
				# Some background colors
				'bg_black'   => 40,  'bg_red'     => 41,
				'bg_green'   => 42,  'bg_brown'   => 43,
				'bg_blue'    => 44,  'bg_magenta' => 45,
				'bg_cyan'    => 46,  'bg_white'   => 47,
			);
		}

		if( self::$noColorTag ){
			$str = $string;
		}else{
			if(substr_count($tag,'|')){ # parse multiple tags
				$tags = explode('|',$tag);
				$str='';
				foreach($tags as $tag){
					$str[]= isset($codes[$tag])?$codes[$tag]:30;
				}
				$str = "\033[".implode(';',$str).'m'.$string."\033[0m";
			}else{
				if( in_array($codes[$tag],array(4,5,7)) ){
					$end = "\033[2".$codes[$tag].'m';
				}else{
					$end = "\033[0m";
				}
				$str = "\033[".(isset($codes[$tag])?$codes[$tag]:30).'m'.$string.$end;
			}
		}

		if(! $stdout)
			return $str;
		fwrite(STDOUT,"$str\n");
	}

	public static function moveUp($nbline=1,$clear=false){
		if($nbline>1 && $clear){
			# clear multiple lines one by one
			for($i=0;$i<$nbline;$i++) fwrite(STDOUT,"\033[1A\033[K");
		}else{
			fwrite(STDOUT,"\033[".$nbline.'A'.($clear?"\033[K":''));
		}
	}

	public static function moveDown($nbline=1,$clear=false){
		if($nbline>1 && $clear){
			# clear multiple lines one by one
			for($i=0;$i<$nbline;$i++) fwrite(STDOUT,"\033[1B\033[K");
		}else{
			fwrite(STDOUT,"\033[".$nbline.'B'.($clear?"\033[K":''));
		}
	}

	#- - Position the Cursor:
	#- \033[<L>;<C>H
		 #- Or
	#- \033[<L>;<C>f
	#- puts the cursor at line L and column C.
	#- - Move the cursor forward N columns:
	#- \033[<N>C
	#- - Move the cursor backward N columns:
	#- \033[<N>D

	#- - Erase to end of line:
	#- \033[K

	#- - Save cursor position:
	#- \033[s
	#- - Restore cursor position:
	#- \033[u
	public static function clear_screen(){
		fwrite(STDOUT,"\033[2J");
	}

	/**
	* helper methods to refresh a progress bar
	* @param mixed  $value     int/float current value relative to $max
	* @param string $msg       message to display if you want to replace the old one.
	* @param bool   $dontclean if set to TRUE then won't replace previous displayed bar but just print at the end of the script
	*/
	public static function refresh_progress_bar($value,$msg=null,$dontclean=false){
		console_app::progress_bar($value,$msg,null,null,null,null,!$dontclean);
	}

	/**
	* set and display a progress bar
	* @param mixed  $val int/float value relative to $max
	* @param string $msg message to display with the bar (you can either pass an array(string msg,string tag))
	* @param int    $w   length of the bar (in character)
	* @param mixed  $max int/float maximum value to display
	* @param string $formatString is a string to custom the display of your progress bar
	*                             this are the replacement made in the string before displaying:
	*                             %V will be replaced by the value
	*                             %S will be replaced by the msg
	*                             %M will be replaced by the maxvalue
	*                             %B will be replaced by the bar
	*                             %P will be replaced by the percent value
	* @param array $style   permit you to custom the bar style array(done_style,todo_style)
	*                       where (todo|done)_style can be a string (character) or an array( (str) char,(str) tag as in tagged_string)
	* @param bool  $refresh set this to true if you want to erase last printed progress
	*                       (you mustn't have any output between this call and the last one to get it work properly)
	*                       normaly you won't have to use this, use the helper methods refresh_progress_bar().
	*/
	public static function progress_bar($val,$msg=null,$max=100,$w=40,$formatString="%S\n\t%B %P %V/%M",$style=array(array('=','bold|green'),' '),$refresh=false){
		static $pgdatas;
		$args = array('val','msg','w','max','formatString','style');
		foreach($args as $arg){
			if( is_null($$arg) ) # set null args to previous values
				$$arg = isset($pgdatas[$arg])?$pgdatas[$arg]:null;
			else # keep trace for next call
				$pgdatas[$arg] = $$arg;
		}
		# clear previously displayed bar
		if($refresh && isset($pgdatas['nbline']) ){
			self::moveUp($pgdatas['nbline'],true);
		}
		# calc some datas
		$good = max(0,round($val*$w/$max));
		$bad  = max(0,$w-$good);

		# make some style
		list($done_chr,$todo_chr) = $style;
		if(is_array($done_chr))
			list($done_chr,$done_tag) = $done_chr;
		if(is_array($todo_chr))
			list($todo_chr,$todo_tag) = $todo_chr;
		$good = str_repeat($done_chr,$good);
		$bad = str_repeat($todo_chr,$bad);
		if( isset($done_tag) )
			$good = console_app::tagged_string($good,$done_tag);
		if( isset($todo_tag) )
			$bad = console_app::tagged_string($bad,$todo_tag);

		# then render the bar
		if( is_array($msg) ) $msg = console_app::tagged_string($msg[0],$msg[1]);
		$bar = '['.$good.$bad.']';
		$per = round($val/$max*100,1);
		$str = str_replace(array('%V','%M','%S','%B','%P'),array($val,$max,$msg,$bar,"$per%"),$formatString)."\n";
		$pgdatas['nbline'] = substr_count($str,"\n");
		fwrite(STDOUT,$str);
	}

	/**
	* print a 2D array as a table
	* @param array $table
	* @param array $styles list of tag/attributes for each table, rows or cells
	*              * table level attributes are:
	*                - (array)  width / maxwidth / dflt (list of attr by colid)
	*                - (string) hchr / vchr / headertag / headers
	*                - (bool)   nolines / noheader
	*              * row level attributes are:
	*    	           - (string) dflt
	*                - (bool)   noline
	*              exemple: => array(rowid => array(colid=>'tag','dflt'=>'tag'),width=>array(colid=>'fixedwidth'))
	*              - dflt can replace colid or rowid to define default rules
	*              - you can explicitely set headers by adding 'headers=>array(colid=>'label');
	*              - avoid automatic headers by adding a 'noheader'=>true
	*              - avoid separating lines by adding 'nolines'=>true to $styles
	*                or set it per row 'noline'=>true/false
	*              - change the default headers style with 'headertag'=>'tag'
	*              - change the chars used for drawing the table like this 'hchr'=>'_' and 'vchr'=>'!'
	*              - set width or maxwidth for cols (not a row attribute!)
	* @param bool  $return if true then return the string instead of printing it to the screen
	*/
	public static function print_table($table,$styles=null,$return=false){
		if(! (is_array($table)  && count($table)) )// && is_array($table[0]) ) )
			return false;
		$table = array_values($table); # ensure that rows start on 0

		#- manage headers for table with non numeric keys
		$headers = array_keys($table[0]);
		$styles = (array) $styles+self::$dflt_styles['table'];

		if(!empty($styles['noheader']) ){
			$hasHeader = false;
		}else{
			if( !empty($styles['headers']) ){
				$headersLabel = $styles['headers'];
			}elseif(is_string($headers[0])){
				foreach($headers as $k=>$v){
					$headersLabel[$v] = $v;
				}
			}
			if(! isset($headersLabel) ){
				$hasHeader = false;
			}else{
				array_unshift($table,$headersLabel);
				# reindex styles to ensure correct rendering
				$tmpstyles = array();
				foreach($styles as $k=>$v){
					$tmpstyles[is_numeric($k)?$k+1:$k] = $v;
				}
				$styles = $tmpstyles;unset($tmpstyles);

				$hasHeader = true;
			}
		}
		/** START OF FUTURE FEATURE TO REPRESENT THE TABLE HORIZONTALY INSTEAD OF VERTICALLY
		if(isset($styles) && !empty($styles['horizontal'])){
			$horTable	= Array();
			foreach( $table as $rowid => $row ){
				$i=0;
				foreach( $row as $colid => $cell ){
					$horTable[$i][$rowid] = $cell;
					$i++;
				}
			}
			$table = $horTable;
			if($hasHeader){
				$styles['dflt'][0]='reverse';
				$hasHeader=false;
			}
			$headers = array_keys($table[0]);
		}
		**/

		# calc width and height for each cols and set default styles as needed
		foreach($table as $rowid=>$row){

			#- set dflt styles if none given
			if(! isset($styles[$rowid] ) )
				$styles[$rowid] = isset($styles['dflt'])?$styles['dflt']:null;

			#- set dflt row line style if none given
			if( (!empty($styles['nolines'])) && ! isset($styles[$rowid]['noline']) )
				$styles[$rowid]['noline'] = true;

			#- manage cols settings
			foreach($row as $colid=>$col){
				#- check dflt cols settings only once
				if(! isset($colStyles[$colid]) ){
					$colStyles[$colid] = (!empty($styles['dflt'][$colid])?$styles['dflt'][$colid]:(!empty($styles['dflt']['dflt'])?$styles['dflt']['dflt']:null));
					$fixWidths[$colid] = (!empty($styles['width'][$colid])?$styles['width'][$colid]:(!empty($styles['width']['dflt'])?$styles['width']['dflt']:null));
					$maxWidths[$colid] = (!empty($styles['maxwidth'][$colid])?$styles['maxwidth'][$colid]:(!empty($styles['maxwidth']['dflt'])?$styles['maxwidth']['dflt']:null));
				}

				# set default col style if needed
				if( empty($styles[$rowid][$colid]) && empty($styles[$rowid]['dflt']) )
					$styles[$rowid][$colid] = $colStyles[$colid];

				#- calc cell sizes
				if($w = $fixWidths[$colid]){
					$table[$rowid][$colid] = $col = wordwrap($col,$w,"\n",true);
					$widths[$colid] = $w;
				}else{
					$maxlen = max(array_map('strlen',explode("\n",$col)));
					if( ($w = $maxWidths[$colid]) && $w < $maxlen){
						$table[$rowid][$colid] = $col = wordwrap($col,$w,"\n",true);
						$maxlen = $w;
					}
					$widths[$colid] = isset($widths[$colid])?max($widths[$colid],$maxlen):$maxlen;
				}
				$nbline = substr_count($col,"\n")+1;
				$heights[$rowid] = isset($heights[$rowid])?max($heights[$rowid],$nbline):$nbline;
			}
		}

		# now render the table
		$twidth = array_sum($widths) + 3*count($widths) + 1;
		$nbr=0;
		$strOut = '';
		$hchr = substr($styles['hchr'],0,1);
		$vchr = substr($styles['vchr'],0,1);

		foreach($table as $rid=>$row){ # each  table rows
			if( ( empty($styles[$rid]['noline']) || $rid ==0 ) && $hchr)
				$strOut .= str_repeat($hchr,$twidth)."\n"; # draw top row border
			$rowlines = array();
			# prepare rows
			foreach($headers as $cid){
				$col = isset($row[$cid])?explode("\n",$row[$cid]):'';
				$colstyle = empty($styles[$rid][$cid])?(!empty($styles[$rid]['dflt'])?$styles[$rid]['dflt']:null):$styles[$rid][$cid];
				# recompose each row line by line
				for($i=0;$i<$heights[$rid];$i++){
					if(! isset($col[$i]) ){
						$rowlines[$i][$cid] = str_repeat(' ',$widths[$cid]);
					}else{
						$cline = ($colstyle && ($nbr||!$hasHeader))?self::tagged_string($col[$i],$colstyle):$col[$i];
						$rowlines[$i][$cid] = $cline.str_repeat(' ',max(0,$widths[$cid]-strlen($col[$i])));
					}
				}
			}
			# print rows
			for($i=0;$i<$heights[$rid];$i++){
				$strRow = $vchr?"$vchr ".implode(" $vchr " ,$rowlines[$i])." $vchr":implode('  ',$rowlines[$i]);
				$strOut .= (( $nbr==0 && $hasHeader)?console_app::tagged_string($strRow,$styles['headertag']):$strRow)."\n";
			}
			$nbr++;
		}
		if($hchr)
			$strOut .= str_repeat($hchr,$twidth)."\n" ; # draw bottom table border
		if($return)
			return $strOut;
		fwrite(STDOUT,$strOut);
	}

	#- public static function print_paginated_table(){	}

	public static function msg($msg,$tag=null){
		if($tag)
			console_app::tagged_string($msg,$tag,1);
		else
			fwrite(STDOUT,"$msg\n");
	}
	/**
	* display an information msg to the user (blue)
	* @param string $msg;
	*/
	public static function msg_info($msg){
		list($tag,$prefx,$sufx) = self::$dflt_styles['info'];
		console_app::msg($prefx.$msg.$sufx,$tag);
		return false;
	}
	/**
	* display an error message
	* @param string $msg
	* @param bool   $fatal stop the script if set to yes
	* @param int    $exitcode code to return on fatal error (-1 is default)
	* @return false
	*/
	public static function msg_error($msg,$fatal=false,$exitcode=-1){
		list($tag,$prefx,$sufx) = self::$dflt_styles['error'];
		$msg = console_app::tagged_string($prefx.($fatal?' FATAL ':'').$msg.$sufx,$tag,false);
		fwrite(STDERR,"$msg\n");
		#- error_log($msg); # do this for Qaleo

		if($fatal)
			exit($exitcode);
		return false;
	}

	/**
	* display a confirmation message (yes|no choice)
	* @param string $msg
	* @param string $tag optionnaly tagg the string as in tagged string
	* @param bool   $dfltIsYes if true then the default value is yes instead of no
	* @param string $answers yes|no replacement for example: allow|deny the yes response is always first response may be the first answer char or the full response
	* @return bool
	*/
	public static function msg_confirm($msg,$tag=null,$dfltIsYes=false,$answers=null){
		list($tag_,$prefx,$sufx) = self::$dflt_styles['confirm'];

		if( null === $answers)
			$answers = $sufx;
		list($yes,$no) = explode('|',$answers,2);
		$dflt=$dfltIsYes?$yes:$no;

		if(! $tag)
			$tag = $tag_;

		$msg = $prefx."$msg\n".
			(console_app::tagged_string(substr($yes,0,1),'underline').substr($yes,1)
				.'|'
				.console_app::tagged_string(substr($no,0,1),'underline').substr($no,1)
			)." ($dflt)";
		$res = console_app::read($tag?console_app::tagged_string($msg,$tag):$msg,$dflt);
		if(preg_match('!^['.substr($yes,0,1).']('.substr($yes,1).')?$!i',$res))
			return true;
		else
			return false;
	}

	/**
	* same as the read method but with the possibility to tagg the displayed message
	*/
	public static function msg_read($msg,$tag='blue',$dflt=null,$check_exit=true){
		return console_app::read(console_app::tagged_string($msg,$tag),$dflt,$check_exit);
	}
	/**
	* lit une entree utilisateur sur STDIN
	* @param string $string message a afficher
	* @param string $dflt   valeur par defaut optionnelle,
	*               si l'utilisateur ne saisie rien alors c'est la valeur par defaut qui sera retourné.
	* @param bool   $check_exit if the user input exit on a read request the program default behaviour is to exit itelf
	*                           you can disablle this feature by setting this to false.
	* return string
	*/
	public static function read($string='Wait for input:',$dflt=null,$check_exit=true){
		$lnOnRead = console_app::$lnOnRead;
		$useReadline = console_app::$useReadline;
		$captureEOT = console_app::$captureEOT;
		if( $lnOnRead )
			$string .= "\n";
		if( $useReadline ){
			$read = readline($string);
			if( $read === false ){ # capture End Of Transmission (CTRL+D)
				if( $captureEOT )
					exit();
				return false;
			}
		}else{
			if($string) fwrite(STDOUT,$string);
			$read=fgets(STDIN,4096);
			if(! strlen($read)){ # capture End Of Transmission (CTRL+D)
				if($captureEOT)
					exit();
				else
					return false;
			}
			# strip newline
			$read = preg_replace('![\r\n]+$!','',$read);
		}

		if($check_exit && $read =='exit'){
			console_app::msg_info('execution stopped by user.');
			exit(0);
		}

		# check default value
		if( (!strlen($read)) && !is_null($dflt))
			return $dflt;
		if( $useReadline )
			readline_add_history($read);
		return strlen($read)?$read:false;
	}

	/**
	* debug function, print the structure of any variable
	* @param mixed  $var
	* @param string $tag
	* @param bool   $exit
	* @deprecated use console_app::dbg() instead
	*/
	public static function show($var,$tag='bold|red',$exit=false){
		$print_func = self::$dflt_styles['dbg']['print_func'];
		console_app::tagged_string($print_func($var,1),$tag,1);
		if($exit) exit;
	}

	/**
	* debug function that print the structures of many vars passed as arguments
	* you can set how this function will print the debug by setting:
	* - console_app::$dflt_styles['dbg']['tag'] for the tag to use
	* - console_app::$dflt_styles['dbg']['breakStr'] for the string to print on break
	* - console_app::$dflt_styles['dbg']['print_func'] structure printing function (print_r|var_dump|var_export or user define function)
	* @param mixed  $args as many vars you want to print debug for
	* @param string $exitOrBreak optionnaly the last arguments can be one of thoose:
	*               - break:   will print the debug and wait for user input to continue the script.
	*                          in this case the user input is returned
	*               - exit:    will print the debug info and stop the script execution.
	*               - askexit: will do the same as break but if anything else than return is enter then the script exit
	*/
	public static function dbg(){
		$args  = func_get_args();

		if( count($args)>1 && in_array($args[count($args)-1],array('exit','break','askexit')) )
			$exitOrBreak = array_pop($args);

		$print_func = self::$dflt_styles['dbg']['print_func'];
		list($tagStart,$tagEnd) = explode(' ',self::tagged_string(' ',self::$dflt_styles['dbg']['tag']));

		fwrite(STDOUT,$tagStart);
		foreach($args as $i=>$a){
			fwrite(STDOUT,($i?"\n":'')."Dbg($i):");
			$print_func($a);
		}
		fwrite(STDOUT,"\n---------------------------------------\n");
		fwrite(STDOUT,$tagEnd);

		if(isset($exitOrBreak)){
			switch($exitOrBreak){
				case 'break':
					return self::msg_read(self::$dflt_styles['dbg']['breakStr'],self::$dflt_styles['confirm'][0]);
				case 'askexit':
					if( self::msg_read(self::$dflt_styles['dbg']['askexitStr'],self::$dflt_styles['confirm'][0]) )
						exit();
					break;
				case 'exit':
					exit();
			}
		}
	}

	/**
	* interactively reconfigure a simple configuration file
	* @param string $cfg_file 		the config file to reconfigure
	* @param string $cfg_template template file to use for the config file
	* @param array  $dflt_vals    you can pass an array of default value for the user prompt
	*                             ie: array('CONFIG_PARAM'=>'PARAM_VALUE'[,...]);
	* @param bool   $exitonerror  will stop the programm on error if set to true
	* @return bool
	* @require write_conf_file(),parse_conf_file()
	*/
	public static function interactive_reconfigure($cfg_file,$cfg_template=null,$dflt_vals=null,$exitonerror=false,$returnconf=false){
		require_once(dirname(__file__).'/fx-conf.php');
		console_app::msg("** Interactive Reconfigure -> $cfg_file **",'blue');

		# prepare the template config array
		if(is_null($cfg_template))
			$cfg_template = $cfg_file;
		if(! is_file($cfg_template)){
			console_app::msg_error("Invalid configuration file will try: $cfg_file");
			if(! is_file($cfg_template = $cfg_file) ){
				if(! is_array($dflt_vals) )
					return console_app::msg_error("Can't reconfigure without any valid configuration template!",$exitonerror);
				else
					$tpl_cfg = $dflt_vals;
			}
		}

		# parse template config
		if( (! (isset($tpl_cfg) && is_array($tpl_cfg))) && ! is_array($tpl_cfg = parse_conf_file($cfg_template,true)) )
			return console_app::msg_error("Empty configuration file. Don't know what to configure",$exitonerror);

		# user reconfigure
		foreach($tpl_cfg as $k=>$v){
			if(isset($dflt_vals[$k]))
				$v = $dflt_vals[$k];
			$tpl_cfg[$k] = console_app::read("set '$k' ($v): ",$v);
		}
		if(! write_conf_file($cfg_file,$tpl_cfg,true) )
			return console_app::msg_error("Can't write file : $cfg_file",$exitonerror);
		fwrite(STDOUT,"configuration DONE.\n".str_repeat('-',70)."\n");
		if($returnconf)
			return $tpl_cfg;
		return true;
	}

	public static function save_history($histFile=null){
		$histFile = is_null($histFile)? console_app::$historyFile : $histFile;
		if(is_null($histFile))
			return false;
		# dump history to file if needed
		if(! readline_write_history($histFile) ){
			return console_app::msg_error("Can't write to history file.");
		}
		# cleanup of history file
		$hist = readline_list_history();
		if( ($histsize = count($hist)) > console_app::$historyMaxLen ){
			$hist = array_slice($hist, $histsize - console_app::$historyMaxLen);
			if(! $fhist = fopen($histFile,'w') ){
				console_app::msg_error("Can't open history file");
			}else{
				fwrite($fhist,implode("\n",$hist));
				fclose($fhist);
			}
		}
	}

	public function __destruct(){
		if(console_app::$useReadline){
			console_app::save_history();
		}
	}
}

?>
