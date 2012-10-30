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

package com.flack.geni.tasks.xmlrpc.am
{
	import com.flack.geni.resources.virtual.Sliver;
	import com.flack.geni.tasks.process.ParseRequestManifestTask;
	import com.flack.shared.FlackEvent;
	import com.flack.shared.SharedMain;
	import com.flack.shared.logging.LogMessage;
	import com.flack.shared.resources.docs.Rspec;
	import com.flack.shared.tasks.TaskError;
	import com.flack.shared.utils.CompressUtil;
	import com.flack.shared.utils.StringUtil;
	
	/**
	 * Lists the sliver's resources at the manager.
	 * 
	 * @author mstrum
	 * 
	 */
	public final class ListSliverResourcesTask extends AmXmlrpcTask
	{
		public var sliver:Sliver;
		
		/**
		 * 
		 * @param newSliver Sliver for which to list resources allocated to the sliver's slice
		 * 
		 */
		public function ListSliverResourcesTask(newSliver:Sliver)
		{
			super(
				newSliver.manager.api.url,
				AmXmlrpcTask.METHOD_LISTRESOURCES,
				newSliver.manager.api.version,
				"List sliver resources @ " + newSliver.manager.hrn,
				"Listing sliver resources for aggregate manager " + newSliver.manager.hrn,
				"List Sliver Resources"
			);
			relatedTo.push(newSliver);
			relatedTo.push(newSliver.slice);
			relatedTo.push(newSliver.manager);
			sliver = newSliver;
		}
		
		override protected function createFields():void
		{
			addOrderedField([sliver.slice.credential.Raw]);
			
			var options:Object = 
				{
					geni_available: false,
					geni_compressed: true,
					geni_slice_urn: sliver.slice.id.full
				};
			var rspecVersion:Object = 
				{
					type: sliver.slice.useInputRspecInfo.type,
					version: sliver.slice.useInputRspecInfo.version.toString()
				};
			if(apiVersion < 2)
				options.rspec_version = rspecVersion;
			else
				options.geni_rspec_version = rspecVersion;
			
			addOrderedField(options);
		}
		
		override protected function afterComplete(addCompletedMessage:Boolean=false):void
		{
			// Sanity check for AM API 2+
			if(apiVersion > 1)
			{
				if(genicode == AmXmlrpcTask.GENICODE_SEARCHFAILED || genicode == AmXmlrpcTask.GENICODE_BADARGS)
				{
					addMessage(
						"No sliver",
						"No sliver found here",
						LogMessage.LEVEL_WARNING,
						LogMessage.IMPORTANCE_HIGH
					);
					super.afterComplete(true);
					return;
				}
				else if(genicode != AmXmlrpcTask.GENICODE_SUCCESS)
				{
					faultOnSuccess();
					return;
				}
			}
			
			try
			{
				var uncompressedRspec:String = CompressUtil.uncompress(data);
				
				addMessage(
					"Manifest received",
					uncompressedRspec,
					LogMessage.LEVEL_INFO,
					LogMessage.IMPORTANCE_HIGH
				);
				
				sliver.manifest = new Rspec(uncompressedRspec,null,null,null, Rspec.TYPE_MANIFEST);
				parent.add(new ParseRequestManifestTask(sliver, sliver.manifest, false, true));
				
				super.afterComplete(addCompletedMessage);
				
			}
			catch(e:Error)
			{
				afterError(
					new TaskError(
						StringUtil.errorToString(e),
						TaskError.CODE_UNEXPECTED,
						e
					)
				);
			}
		}
		
		override protected function afterError(taskError:TaskError):void
		{
			sliver.status = Sliver.STATUS_FAILED;
			SharedMain.sharedDispatcher.dispatchChanged(
				FlackEvent.CHANGED_SLIVER,
				sliver,
				FlackEvent.ACTION_STATUS
			);
			
			super.afterError(taskError);
		}
		
		override protected function runCancel():void
		{
			sliver.status = Sliver.STATUS_UNKNOWN;
			SharedMain.sharedDispatcher.dispatchChanged(
				FlackEvent.CHANGED_SLIVER,
				sliver,
				FlackEvent.ACTION_STATUS
			);
		}
	}
}