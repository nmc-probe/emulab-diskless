import java.awt.Graphics;
import java.awt.Color;
import java.awt.Rectangle;
import java.util.Vector;
import java.util.Enumeration;

public class Palette {
    private Thingee newNode;
    private Thingee newLAN;
    private Thingee trash;

    public Palette() {
	newNode = new NodeThingee( "New Node" );
	newNode.move( 40, 100 );
	newNode.linkable = false;
	newNode.trashable = false;

	newNode.propertyEditable = false;

	newLAN  = new LanThingee( "New LAN" );
	newLAN.move( 40, 180 );
	newLAN.linkable = false;
	newLAN.trashable = false;
	newLAN.propertyEditable = false;

	trash = new TrashThingee( "trash" );
	trash.move( 40, 420 );
	trash.linkable = false;
	trash.trashable = false; // very zen.
	trash.moveable = false;
	trash.propertyEditable = false;
    }

    public void paint( Graphics g ) {
	newNode.draw( g );
	newLAN.draw( g );
	trash.draw( g );
    }

    public boolean hitTrash( int x, int y ) {
	return trash.clicked(x, y);
    }

    public Thingee clicked( int x, int y ) {
	if (newNode.clicked(x, y)) {
	    return newNode;
	} else if (newLAN.clicked( x, y )) {
	    return newLAN;
	} else {
	    return null;
	}
    }
};
