public class LinkPropertiesArea extends PropertiesArea
{
    public boolean iCare( Thingee t ) {
	return (t instanceof LinkThingee && !(t instanceof LanLinkThingee));
    }

    public String getName() { return "Link Properties"; }
 
    public LinkPropertiesArea() 
    {
	super();
	addProperty("name", "name:", "");
	addProperty("bandwidth", "bandwidth(Mb/s):", "100");
	addProperty("latency", "latency(ms):", "0");
	addProperty("loss", "loss rate(0.0 - 1.0):", "0.0");	
    }
};
