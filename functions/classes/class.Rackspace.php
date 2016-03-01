<?php


/**
 *	phpIPAM Rackspace class
 */

class phpipam_rack extends Tools {

    /* private variables */
    public $rack_sizes = array();          // (array) all racksizes
    public $all_racks = false;              // (object) list of all racks
    private $rack_content = array();        // (array) content of rack

	/* object holders */
	public $Result;							// (object) for Result printing
	protected $Database;					// (object) for Database connection
	public $Log;							// (object) for Logging connection

    /* rack classes */
    protected $Rack;
    protected $Drawer;
    protected $RackContent;



	/**
	 * __construct function
	 *
	 * @access public
	 */
	public function __construct (Database_PDO $database) {
		# Save database object
		$this->Database = $database;
		# initialize Result
		$this->Result = new Result ();
		# Log object
		$this->Log = new Logging ($this->Database);

		# set racksizes
		$this->define_rack_sizes ();
		# initialize rack
        $this->Drawer = new RackDrawer();
	}





	/**
	 *	@definitions and default methods
	 *	--------------------------------
	 */

    /**
     * Defines all possible rack sizes
     *
     * @access private
     * @return void
     */
    private function define_rack_sizes () {
        $this->rack_sizes = array(14, 20, 24, 30, 35, 40, 44, 45, 48);
    }


    /**
     * Fetches and returns all racks from database
     *
     * @access public
     * @return void
     */
    public function fetch_all_racks () {
        $all_racks = $this->fetch_all_objects("racks", "name", "acs");
        // reorder
        if ($all_racks==false) {
            $this->all_racks = false;
        }
        else {
            // reindex
            foreach ($all_racks as $r) {
                $out[$r->id] = $r;
            }
            // save
            $this->all_racks = (object) $out;
        }
    }

    /**
     * Fetches details about specific rack
     *
     * @access public
     * @param mixed $id
     * @return void
     */
    public function fetch_rack_details ($id) {
        // first check all_racks
        if (isset($this->all_racks->$id)) {
            return $this->all_racks->$id;
        }
        else {
            return $this->fetch_object("racks", "id", $id);
        }
    }

    /**
     * Fetches all devices attached to rack
     *
     * @access public
     * @param mixed $id
     * @return void
     */
    public function fetch_rack_devices ($id) {
        return $this->fetch_multiple_objects ("devices", "rack", $id, "rack_start", true);
    }






	/**
	 *	@draw rack methods
	 *	--------------------------------
	 */

    /**
     * Prepare rack object and content
     *
     * @access public
     * @param int $id       // rack id
     * @return void
     */
    public function draw_rack ($id, $deviceId = false) {
        // fetch rack details
        $rack = $this->fetch_rack_details ($id);
        // fetch rack devices
        $devices = $this->fetch_rack_devices ($id);

        // set name
        $this->rack_name = $rack->name;
        $this->rack_size = $rack->size;

        // set content
        if ($devices!==false) {
            foreach ($devices as $d) {
                // add initial location
                $rd = array("id"=>$d->id,
                            "name"=>$d->hostname,
                            "startLocation"=>$d->rack_start,
                            "size"=>$d->rack_size,
                            "rackName"=>$rack->name
                            );
                // if startlocation is not set
                $rd['startLocation'] -= 1;
                // save content
                $this->rack_content[] = new RackContent ($rd);
            }
        }

        // create rack
        $this->set_rack ();
        // set active device
        if ($deviceId!==false) {
            $this->set_active_rack_device ($deviceId);
        }
        // draw rack drawer
        $this->set_draw_rack ();
    }

    /**
     * Sets new rack with details
     *
     * @access private
     * @return void
     */
    private function set_rack () {
        // initialize
        $this->Rack = new Rack (array("name"=>$this->rack_name, "content"=>$this->rack_content));
        // set rack size
        $this->Rack->setSpace($this->rack_size);
    }

    /**
     * Set active rack devide.
     *
     * @access public
     * @param mixed $id         // device id
     * @return void
     */
    public function set_active_rack_device ($id) {
        foreach ($this->Rack->getContent() as $content) {
            if ($content->getId() == $id) {
                $content->setActive();
            }
        }
    }

    /**
     * Draw rack
     *
     * @access private
     * @return void
     */
    private function set_draw_rack () {
        $this->Drawer->draw ($this->Rack);
    }
}










/***********************************************************************************************************************
 * Class Definitions below
 **********************************************************************************************************************/

/**
 * Class RackDrawer
 * @package GlasOperator\Rack
 */
class RackDrawer extends Common_functions
{
    /** @var Rack */
    private $rack;
    /** @var resource */
    private $template;

    /**
     * @param Rack $rack
     */
    public function draw(Rack $rack)
    {
        $this->rack = $rack;
        $this->template = imagecreatefrompng($this->createURL().BASE."/css/1.2/images/blankracks/" . $this->rack->getSpace() . ".png");
        $this->drawNameplate();
        $this->drawContents();

        header("Content-type: image/png");
        imagepng($this->template);
        imagedestroy($this->template);
    }

    /**
     * Draws the name plate of the rack itself
     */
    private function drawNameplate()
    {
        $nameplate = imagecreate(150, 20);
        imagecolorallocate( $nameplate, 255, 255, 255 ); // Allocate a background color (first color assigned)
        $textColour = imagecolorallocate($nameplate, 0, 0, 0);
        $this->imageCenterString($nameplate, 3, $this->rack->getName(), $textColour);
        imagecopy($this->template, $nameplate, 52, 1, 0, 0, 150, 20);
    }

    /**
     * Inserts the passed text in fontsize and color into the passed image
     *
     * @param resource $img
     * @param int $font
     * @param string $text
     * @param int $color
     */
    private function imageCenterString($img, $font, $text, $color)
    {
        if ($font < 0 || $font > 5) {
            $font = 0;
        }
        $num = Array( Array(4.6, 6), Array(4.6, 6), Array(5.6, 12), Array(6.5, 12), Array(7.6, 16), Array(8.5, 16));
        $width = ceil(strlen($text) * $num[$font][0]);
        $x = imagesx($img) - $width - 8;
        $y = Imagesy($img) - ($num[$font][1] + 2);
        imagestring($img, $font, $x/2, $y/2, $text, $color);
    }

    /**
     * Draws a content slot into the result
     */
    private function drawContents()
    {
        foreach ($this->rack->getContent() as $content)
        {
            $pixelSize = 20 * $content->getSize();

            $img = imagecreate(200, $pixelSize);
            $this->drawContent($content, $img, $content->getName());

            $yPos = 22 + 20 * ($this->rack->getSpace() - ($content->getStartLocation() + $content->getSize()));

            imagecopy($this->template, $img, 27, $yPos, 0, 0, 200, $pixelSize);
            imagedestroy($img);
        }
    }

    /**
     * @param RackContent $content
     * @param resource $img
     * @param string $name
     */
    private function drawContent(RackContent $content, $img, $name)
    {
        if ($content->isActive()) {
            imagecolorallocate($img, 207, 232, 255); // Allocate a background color (first color assigned) - active
            $textColour = imagecolorallocate($img, 0, 0, 0);
            $lineColour = imagecolorallocate($img, 122, 137, 150);
        } else {
            imagecolorallocate($img, 230, 230, 230); // Allocate a background color (first color assigned)  - all
            $textColour = imagecolorallocate($img, 0, 0, 0);
            $lineColour = imagecolorallocate($img, 122, 137, 150);
        }

        $this->imageCenterString($img, 3, $name, $textColour);
        imageline($img, 0, 0, 200, 0, $lineColour);
        imageline($img, 0, imagesy($img) - 1, 200, imagesy($img) - 1, $lineColour);
    }
}

/**
 * Class Model
 * @package GlasOperator\Rack
 */
class Model
{
    /**
     * @param array $fields
     */
    public function __construct(array $fields)
    {
        foreach ($fields as $field => $value)
        {
            $setter = 'set' . ucfirst($field);
            if (method_exists($this, $setter)) {
                $this->$setter($value);
            }
        }
    }
}

/**
 * Class Rack
 * @package GlasOperator\Rack
 */
class Rack extends Model
{
    /** @var string */
    private $name;
    /** @var string */
    private $space = 48;
    /** @var RackContent[] */
    private $content;
    /** @var bool */
    private $active = false;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getSpace()
    {
        return $this->space;
    }

    /**
     * @param string $space
     */
    public function setSpace($space)
    {
        $this->space = $space;
    }

    /**
     * @return RackContent[]
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param RackContent[] $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @return boolean
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * @param boolean $active
     */
    public function setActive($active)
    {
        $this->active = $active;
    }
}

/**
 * Class RackContent
 * @package GlasOperator\Rack
 */
class RackContent extends Model
{
    /** @var int */
    private $id;
    /** @var string */
    private $name;
    /** @var bool */
    private $active;
    /** @var int */
    private $startLocation;
    /** @var int */
    private $size;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return boolean
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * @param boolean $active
     */
    public function setActive($active = true)
    {
        $this->active = $active;
    }

    /**
     * @return int
     */
    public function getStartLocation()
    {
        return $this->startLocation;
    }

    /**
     * @param int $startLocation
     */
    public function setStartLocation($startLocation)
    {
        $this->startLocation = $startLocation;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }
}

?>