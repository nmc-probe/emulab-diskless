﻿/* GENIPUBLIC-COPYRIGHT
* Copyright (c) 2008-2011 University of Utah and the Flux Group.
* All rights reserved.
*
* Permission to use, copy, modify and distribute this software is hereby
* granted provided that (1) source code retains these copyright, permission,
* and disclaimer notices, and (2) redistributions including binaries
* reproduce the notices in supporting documentation.
*
* THE UNIVERSITY OF UTAH ALLOWS FREE USE OF THIS SOFTWARE IN ITS "AS IS"
* CONDITION.  THE UNIVERSITY OF UTAH DISCLAIMS ANY LIABILITY OF ANY KIND
* FOR ANY DAMAGES WHATSOEVER RESULTING FROM THE USE OF THIS SOFTWARE.
*/

package protogeni.communication
{
	import com.mattism.http.xmlrpc.MethodFault;
	
	import flash.events.ErrorEvent;
	
	import protogeni.GeniEvent;
	import protogeni.StringUtil;
	import protogeni.resources.Slice;
	import protogeni.resources.Sliver;
	import protogeni.resources.VirtualComponent;
	import protogeni.resources.VirtualNode;
	
	/**
	 * Gets the sliver status using the ProtoGENI API
	 * 
	 * @author mstrum
	 * 
	 */
	public final class RequestSliverStatus extends Request
	{
		public var sliver:Sliver;
		
		public function RequestSliverStatus(newSliver:Sliver):void
		{
			super("SliverStatus",
				"Getting the sliver status on " + newSliver.manager.Hrn + " on slice named " + newSliver.slice.hrn,
				CommunicationUtil.sliverStatus,
				true,
				true);
			sliver = newSliver;
			sliver.changing = true;
			sliver.message = "Checking status";
			
			// Build up the args
			op.addField("slice_urn", sliver.slice.urn.full);
			op.addField("credentials", new Array(sliver.slice.credential));
			op.setUrl(sliver.manager.Url);
		}
		
		override public function start():Operation {
			Main.geniDispatcher.dispatchSliceChanged(sliver.slice, GeniEvent.ACTION_STATUS);
			return op;
		}
		
		override public function complete(code:Number, response:Object):*
		{
			var old:Slice;
			var oldSliver:Sliver;
			if (code == CommunicationUtil.GENIRESPONSE_SUCCESS)
			{
				sliver.status = response.value.status;
				sliver.state = response.value.state;
				for(var sliverId:String in response.value.details)
				{
					var sliverDetails:Object = response.value.details[sliverId];
					
					var virtualComponent:VirtualComponent = sliver.getBySliverId(sliverId);
					if(virtualComponent != null)
					{
						virtualComponent.status = sliverDetails.status;
						virtualComponent.state = sliverDetails.state;
						virtualComponent.error = sliverDetails.error;
					}
				}
				sliver.changing = !sliver.StatusFinalized;
				if(sliver.changing) {
					if(sliver.status == Sliver.STATUS_FAILED)
						sliver.message = "Failed";
					else if(sliver.status == Sliver.STATUS_READY)
						sliver.message = "Ready";
					else
						sliver.message = "Status is " + sliver.status;
				}
				else
					sliver.message = "Status is " + sliver.status;
				
				old = Main.geniHandler.CurrentUser.slices.getByUrn(sliver.slice.urn.full);
				if(old != null)
				{
					oldSliver = old.slivers.getByManager(sliver.manager);
					if(oldSliver != null)
						oldSliver.copyStatusFrom(sliver);
				}
			}
			// Slice was deleted
			else if(code == CommunicationUtil.GENIRESPONSE_SEARCHFAILED) {
				sliver.removeOutsideReferences();
				if(sliver.slice.slivers.contains(sliver))
					sliver.slice.slivers.remove(sliver);
				old = Main.geniHandler.CurrentUser.slices.getByUrn(sliver.slice.urn.full);
				if(old != null)
				{
					oldSliver = old.slivers.getByUrn(sliver.urn.full);
					if(oldSliver != null) {
						oldSliver.removeOutsideReferences();
						old.slivers.remove(old.slivers.getByUrn(sliver.urn.full));
					}
				}
				sliver.changing = false;
				sliver.message = "Status was deleted";
			}
			
			return null;
		}
		
		public function failed(msg:String = ""):void {
			sliver.changing = true;
			sliver.message = "Checking status failed";
		}
		
		override public function fail(event:ErrorEvent, fault:MethodFault):* {
			failed(fault.getFaultString());
			return null
		}
		
		override public function cleanup():void {
			super.cleanup();
			Main.geniDispatcher.dispatchSliceChanged(sliver.slice, GeniEvent.ACTION_STATUS);
		}
	}
}
