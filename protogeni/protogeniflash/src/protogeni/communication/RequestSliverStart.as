﻿/* GENIPUBLIC-COPYRIGHT
 * Copyright (c) 2008, 2009 University of Utah and the Flux Group.
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
  class RequestSliverStart extends Request
  {
    public function RequestSliverStart(newManager : ComponentManager,
                                       newNodes : ActiveNodes,
									   newSliceUrn) : void
    {
      super(newManager.getName());
      manager = newManager;
      nodes = newNodes;
	  sliceUrn = newSliceUrn;
    }

    override public function cleanup() : void
    {
      super.cleanup();
    }

    override public function start(credential : Credential) : Operation
    {
      nodes.changeState(manager, ActiveNodes.CREATED, ActiveNodes.BOOTED);
      opName = "Booting Sliver";
      op.reset(Geni.startSliver);
      op.addField("slice_urn", sliceUrn);
      op.addField("credentials", new Array(manager.getSliver()));
      //? op.addField("impotent", Request.IMPOTENT);
      op.setUrl(manager.getUrl());
      return op;
    }

    override public function complete(code : Number, response : Object,
                                      credential : Credential) : Request
    {
      if (code == 0)
      {
        nodes.commitState(manager);
      }
      else
      {
        nodes.revertState(manager);
      }
      return null;
    }

    var manager : ComponentManager;
    var nodes : ActiveNodes;
	var sliceUrn : String;
  }
}
