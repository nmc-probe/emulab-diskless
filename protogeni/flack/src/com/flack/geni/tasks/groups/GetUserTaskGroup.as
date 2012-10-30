/*
 * Copyright (c) 2008-2012 University of Utah and the Flux Group.
 * 
 * {{{GENIPUBLIC-LICENSE
 * 
 * GENI Public License
 * 
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and/or hardware specification (the "Work") to
 * deal in the Work without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Work, and to permit persons to whom the Work
 * is furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Work.
 * 
 * THE WORK IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE WORK OR THE USE OR OTHER DEALINGS
 * IN THE WORK.
 * 
 * }}}
 */

package com.flack.geni.tasks.groups
{
	import com.flack.geni.GeniMain;
	import com.flack.geni.resources.GeniUser;
	import com.flack.geni.resources.docs.GeniCredential;
	import com.flack.geni.resources.sites.GeniAuthority;
	import com.flack.geni.resources.virtual.Slice;
	import com.flack.geni.tasks.groups.slice.GetSliceTaskGroup;
	import com.flack.shared.FlackEvent;
	import com.flack.shared.SharedMain;
	import com.flack.shared.logging.LogMessage;
	import com.flack.shared.tasks.ParallelTaskGroup;
	import com.flack.shared.tasks.SerialTaskGroup;
	import com.flack.shared.tasks.Task;
	import com.flack.shared.tasks.TaskError;
	
	/**
	 * Gets the user's credential and slices
	 * 
	 * 1. If shouldResolveUser:
	 *     If using user credential: ResolveUserSaTask
	 *     If using slice credential: Start 2
	 * 2. If shouldGetSlices or using slice credential: For each slice: GetSliceTaskGroup
	 * 
	 * @author mstrum
	 * 
	 */
	public final class GetUserTaskGroup extends SerialTaskGroup
	{
		public var user:GeniUser;
		public var shouldResolveUser:Boolean;
		public var shouldGetSlices:Boolean;
		
		/**
		 * 
		 * @param newUser User to get everything for
		 * @param newShouldResolveUser Resolve the user to get the list of slices, keys, etc.
		 * @param newShouldGetSlices Get the slices?
		 * 
		 */
		public function GetUserTaskGroup(newUser:GeniUser,
										 newShouldResolveUser:Boolean = true,
										 newShouldGetSlices:Boolean = true)
		{
			super(
				"Get user",
				"Gets all user-related information"
			);
			relatedTo.push(newUser);
			forceSerial = true;
			
			user = newUser;
			shouldResolveUser = newShouldResolveUser && newUser.authority != null;
			shouldGetSlices = newShouldGetSlices;
		}

		override protected function runStart():void
		{
			// First run
			if(tasks.length == 0)
			{
				if(user.credential == null)
				{
					afterError(
						new TaskError(
							"No user certificate!",
							TaskError.CODE_PROBLEM
						)
					);
					return;
				}
				if(shouldResolveUser)
				{
					if(user.credential.type == GeniCredential.TYPE_USER)
						add(new ResolveUserTaskGroup(GeniMain.geniUniverse.user));
					else if(user.credential.type == GeniCredential.TYPE_SLICE)
						getResources();
				}
				else if(shouldGetSlices)
					getResources();
			}
			super.runStart();
		}
		
		override public function completedTask(task:Task):void
		{
			if(task is ResolveUserTaskGroup && shouldGetSlices)
				getResources();
			super.completedTask(task);
		}
		
		private function getResources():void
		{
			if(user.slices.length == 0)
				afterComplete();
			else
			{
				var getSlices:ParallelTaskGroup =
					new ParallelTaskGroup(
						"Get slices",
						"Gets the slices for the user"
					);
				for each(var slice:Slice in user.slices.collection)
				{
					getSlices.add(
						new GetSliceTaskGroup(
							slice,
							user.authority != null && user.authority.type != GeniAuthority.TYPE_EMULAB,
							user.authority == null
						)
					);
				}
				add(getSlices);
			}
		}
		
		override protected function afterComplete(addCompletedMessage:Boolean=false):void
		{
			addMessage(
				"Finished",
				"Completed getting information for user " + user.name + " along with "+user.slices.length+" slices.",
				LogMessage.LEVEL_INFO,
				LogMessage.IMPORTANCE_HIGH
			);
			SharedMain.sharedDispatcher.dispatchChanged(
				FlackEvent.CHANGED_USER,
				user,
				FlackEvent.ACTION_POPULATED
			);
			super.afterComplete(addCompletedMessage);
		}
	}
}