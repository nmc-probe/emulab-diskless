#! /usr/bin/env python
#
# hypview - HyperViewer application.
# For description of script args, invoke with any dash arg or see the "usage:" message below.
#
# EMULAB-COPYRIGHT
# Copyright (c) 2004 University of Utah and the Flux Group.
# All rights reserved.
#
# Permission to use, copy, modify and distribute this software is hereby
# granted provided that (1) source code retains these copyright, permission,
# and disclaimer notices, and (2) redistributions including binaries
# reproduce the notices in supporting documentation.
#
# THE UNIVERSITY OF UTAH ALLOWS FREE USE OF THIS SOFTWARE IN ITS "AS IS"
# CONDITION.  THE UNIVERSITY OF UTAH DISCLAIMS ANY LIABILITY OF ANY KIND
# FOR ANY DAMAGES WHATSOEVER RESULTING FROM THE USE OF THIS SOFTWARE.
#

##import pdb

import string
import sys
import types
import os

import hv
# Constants from HypData.h
HV_ANIMATE = 0

import exptToHv
from hvFrameUI import *
from OpenGL.GL import * 

from wxPython.wx import *
from wxPython.glcanvas import wxGLCanvas

# A wxPython application object.
class hvApp(wxApp):
    
    ##
    # The app initialization.
    def OnInit(self):
	
	filename = filearg = default_project = project = experiment = root = None
	if os.environ.has_key("EMULAB_PROJECT"):    # Default project name to use.
	    default_project = project = os.environ["EMULAB_PROJECT"]

	# Any dash argument prints a usage message and exits.
	if len(sys.argv) == 2 and sys.argv[1][0] == '-': 
	    print '''Hyperviewer usage:
  No args - Starts up the GUI.	Use the File/Open menu item to read a topology.
  One arg - A .hyp file name.  Read it in and start the GUI, e.g.:
      ./hypview BigLan.hyp
  Two args - Project and experiment names in the database.
      Get the topology from XMLRPC, make a .hyp file, start as above.
      ./hypview testbed BigLan
  Three args - Project and experiment names, plus an optional root node name.
      ./hypview testbed BigLan clan'''
	    sys.exit()
	    pass
	
	# Given command-line argument(s), attempt to read in a topology.
	# One command-line argument: read from a .hyp file.
	# (File/experiment input is also in the OnOpenFile and OnOpenExperiment methods.)
	elif len(sys.argv) == 2:
	    filename = filearg = sys.argv[1]
	    print "Reading file:", filename
	    pass
	
	# Two args: read an experiment from the DB via XML-RPC, and make a .hyp file.
	elif len(sys.argv) == 3:
	    project = sys.argv[1]
	    if project == "":
		project = default_project
	    experiment = sys.argv[2]
	    print "Getting project:", project + ", experiment:", experiment
	    filename = exptToHv.getExperiment(project, experiment)
	    pass

	# Three args: experiment from database, with optional graph root node.
	elif len(sys.argv) == 4:
	    project = sys.argv[1]
	    if project == "":
		project = default_project
	    experiment = sys.argv[2]
	    root = sys.argv[3]
	    print "Getting project:", project + ", experiment:", experiment \
		  + ", root node:", root
	    filename = exptToHv.getExperiment(project, experiment, root)
	    pass

	self.frame = hvFrame(None, -1, "wxHyperViewer", (100,0), (750,750))
	self.frame.app = self	# A back-reference here from the frame.
	self.openDialog = hvOpen(None, -1, "Open HyperViewer Data")
	self.openDialog.app = self
	self.usageDialog = UsageDialogUI(None, -1, "HyperViewer Usage")
	

	# Initialize the text fields in the File/Open dialog.
	if filearg:
	    self.openDialog.FileToOpen.SetValue(filearg) 
        self.openDialog.LoginName.SetValue(exptToHv.login_id)
	if project:
	    self.openDialog.ProjectName.SetValue(project)
	elif default_project:
	    self.openDialog.ProjectName.SetValue(default_project)
	if experiment:
	    self.openDialog.ExperimentName.SetValue(experiment)
	    self.openDialog.ExperimentName.SetFocus()
	if root:
	    self.openDialog.ExperimentRoot.SetValue(root)
	
	# Make the top-level window visible.
	self.frame.Show()
	self.SetTopWindow(self.frame)

	if filename:
	    if type(filename) is types.ListType:
		print "Failed to read experiment from database."
		exptError = '%s %s\n%s' % tuple(filename[1:4])
		print exptError
		self.frame.shutdown()
	    else:
		if not self.frame.ReadTopFile("wxHyperViewer", filename):
		    #print "Could not open ", filename # Already printed error in C++.
		    self.frame.shutdown() 
		pass
	    pass

	return True			# OnInit success.
    pass

# hvFrame - The semantics (methods) of the UI of the application.  Notice that this object
# inherits from hvFrameUI in hvFrameUI.py, which is generated by wxGlade from hvgui.wxg .
class hvFrame(hvFrameUI):
    
    ##
    # Frame initialization.
    def __init__(self, *args, **kwds):
	# Set up the wxGlade part.
	hvFrameUI.__init__(self, *args, **kwds)

	# Remember the original width of the controls panel in the splitter.
	self.controlsWidth = self.window_1.GetSize().width - self.window_1.GetSashPosition()
	
	self.vwr = None		# Load data and create the viewer later in ReadTopFile.
	self.currNode = None	# Nothing selected at first.

	# Control events.  (HyperViewer events are connected after loading data.)
	# EVT_ handler-setting functions are defined in site-packages/wxPython/wx.py .
	# See http://www.wxwindows.org/manuals/2.4.2/wx470.htm#eventhandlingoverview .
	EVT_TEXT_ENTER(self.NodeName, -1, self.OnNodeName)
	EVT_CHECKBOX(self.DrawSphere, -1, self.OnDrawSphere)
	EVT_CHECKBOX(self.DrawNodes, -1, self.OnDrawNodes)
	EVT_CHECKBOX(self.DrawLinks, -1, self.OnDrawLinks)
	EVT_CHECKBOX(self.KeepAspect, -1, self.OnKeepAspect)
	EVT_CHECKBOX(self.LabelToRight, -1, self.OnLabelToRight)
	EVT_BUTTON(self.GoToTop, -1, self.OnGoToTop)
	EVT_BUTTON(self.ShowLinksIn, -1, self.OnShowLinksIn)
	EVT_BUTTON(self.HideLinksIn, -1, self.OnHideLinksIn)
	EVT_CHECKBOX(self.DescendLinksIn, -1, self.OnDescendLinksIn)
	EVT_BUTTON(self.ShowLinksOut, -1, self.OnShowLinksOut)
	EVT_BUTTON(self.HideLinksOut, -1, self.OnHideLinksOut)
	EVT_CHECKBOX(self.DescendLinksOut, -1, self.OnDescendLinksOut)
	EVT_BUTTON(self.HelpButton, -1, self.OnUsage)
	EVT_CHOICE(self.LabelsMode, -1, self.OnLabelsMode)
	EVT_MENU(self, 1, self.OnOpen)
	EVT_MENU(self, 2, self.OnExit)
	EVT_MENU(self, 3, self.OnUsage)
	EVT_SPINCTRL(self.CountGenNode, -1, self.OnCountGenNode)
	EVT_CHAR(self.CountGenNode, self.OnCountGenNode_CHAR)
	EVT_SPINCTRL(self.CountGenLink, -1, self.OnCountGenLink)
	EVT_CHAR(self.CountGenLink, self.OnCountGenLink_CHAR)
	EVT_SCROLL_ENDSCROLL(self.AnimStepCount, self.OnAnimStepCount)	  # Windows
	EVT_SLIDER(self.AnimStepCount, -1, self.OnAnimStepCount)	  # GTK
	
	# Mouse-generated events.
	EVT_LEFT_DOWN(self.hypView, self.OnClick)
	EVT_LEFT_UP(self.hypView, self.OnClick)
	EVT_MIDDLE_DOWN(self.hypView, self.OnClick)
	EVT_MIDDLE_UP(self.hypView, self.OnClick)
	EVT_MOTION(self.hypView, self.OnMove)
	EVT_SIZE(self.hypView, self.OnResizeCanvas)

	# Other events.
	EVT_SIZE(self.window_1, self.OnResizeWindow)
	EVT_SPLITTER_SASH_POS_CHANGED(self.window_1, -1, self.OnSashChanged)
	EVT_CLOSE(self, self.OnExit)
	# These do nothing until the vwr is instantiated below.
	EVT_IDLE(self.hypView, self.OnIdle)
	EVT_PAINT(self.hypView, self.OnPaint)
	
	pass
    
    ##
    # Close the top-level windows, so the MainLoop exits.
    def shutdown(self):
	EVT_CLOSE(self, None)		# Prevent infinite loop.
	self.app.openDialog.Close(True)
	self.app.usageDialog.Close(True)
	self.Close(True)
	self.app.ExitMainLoop()
	pass
    
    ##
    # Read in a topology file and instantiate the C++ Hyperviewer object.
    # Returns True on success, False on failure.
    def ReadTopFile(self, name, file):
	self.SetTitle(name + " " + file)
	
	# Select the OpenGL Graphics Context from the wxGLCanvas in the hvFrameUI.
	self.hypView.SetCurrent()   

	# Get window info from the wxWindow base class of the wxGLCanvas object.
	if os.name == 'nt':
	    # GetHandler returns the platform-specific handle of the physical window:
	    # HWND for Windows, Widget for Motif or GtkWidget for GTK.
	    window = self.hypView.GetHandle()
	else:
	    # The wxGLCanvas has the graphics context and does the SwapBuffers for us on GTK.
	    window = self.hypView.this
	    pass
	##print "self.hypView", self.hypView, "window", window
	# GetSizeTuple is the wxPython version of GetSize.  Returns size of the
	# entire window in pixels, including title bar, border, scrollbars, etc.
	width, height = self.hypView.GetSizeTuple()

	# Instantiate and initialize the SWIG'ed C++ HypView object, loading graph data.
	if self.vwr is not None:
	    ### Give up on hvReadFile; the cleanup logic is busted.  Always make a new HypView.
	    ##print "hvkill", self.vwr
	    ##hv.hvKill(self.vwr)  ## And don't even clean up the old one.

	    # This is *really* evil... It still fails every other time, so do it twice.  :-(
	    self.vwr = hv.hvMain([str(name), str(file)], # Must be non-unicode strings.
	                         window, width, height)  # Win32 needs the window info.
	    ##hv.hvKill(self.vwr)
	    self.vwr = None

	self.vwr = hv.hvMain([str(name), str(file)], # Must be non-unicode strings.
			     window, width, height)  # Win32 needs the window info.
	if self.vwr is None:			     # Must have been a problem....
	    return False

	self.OnGoToTop(None)			     # Show info for the top node.
	
	return True
    
    ##
    # Draw the OpenGL content and make it visible.
    def DrawGL(self):
	##print "in DrawGL"
	self.vwr.drawFrame()
	pass
    
    ##
    # The GUI displays information about the currently selected node.
    def SelectedNode(self, node):
	##print node
	if string.find(node, "|") == -1:	# Links are "node1|node2".
	    self.currNode = node
	    self.NodeName.Clear()
	    self.NodeName.WriteText(node)
	    self.ChildCount.SetLabel(str(self.vwr.getChildCount(node)))

	    linksIn = self.vwr.getIncomingCount(node)
	    self.LabelLinksIn.SetLabel(
		"Non-tree Links in: " + str(linksIn))
	    self.OnDescendLinksIn(None)

	    linksOut = self.vwr.getOutgoingCount(node)
	    self.LabelLinksOut.SetLabel(
		"Non-tree Links out: " + str(linksOut))
	    self.OnDescendLinksOut(None)
	pass
    
    ##
    # Event handling for the HyperViewer canvas.
    # Go to a node when its name is typed and Enter is pressed.
    def OnNodeName(self, cmdEvent):
	if self.vwr is None:
	    return
	node = self.NodeName.GetValue()
	self.vwr.gotoNode(node, HV_ANIMATE)

	# Simulate picking the node as well.
	hv.selectCB(node, 0, 0);
	self.SelectedNode(node)

	self.DrawGL()
	pass
    # Check boxes control boolean state.
    def OnDrawSphere(self, cmdEvent):
	if self.vwr is None:
	    return
	self.vwr.setSphere(self.DrawSphere.IsChecked())
	self.DrawGL()
	pass
    def OnDrawNodes(self, cmdEvent):
	if self.vwr is None:
	    return
	self.vwr.setDrawNodes(self.DrawNodes.IsChecked())
	self.DrawGL()
	pass
    def OnDrawLinks(self, cmdEvent):
	if self.vwr is None:
	    return
	self.vwr.setDrawLinks(self.DrawLinks.IsChecked())
	self.DrawGL()
	pass
    def OnKeepAspect(self, cmdEvent):
	if self.vwr is None:
	    return
	self.vwr.setKeepAspect(self.KeepAspect.IsChecked())
	self.DrawGL()
	pass
    def OnLabelToRight(self, cmdEvent):
	if self.vwr is None:
	    return
	self.vwr.setLabelToRight(self.LabelToRight.IsChecked())
	self.DrawGL()
	pass
    
    ##
    # Buttons issue commands.
    def OnGoToTop(self, buttonEvent):
	if self.vwr is None:
	    return

	# Tell the HypView to reset.
	self.vwr.gotoCenterNode(HV_ANIMATE)

	# Simulate picking the top node.
	##ctr = self.vwr.getGraphCenter()
	ctr = hv.getGraphCenter()
	##print "center node", ctr
	hv.selectCB(ctr, 0, 0);

	# Update the node info and draw.
	self.SelectedNode(ctr)
	self.DrawGL()
	pass

    def OnShowLinksIn(self, buttonEvent):
	self.vwr.setDrawBackTo(hv.getSelected(), 1, self.DescendLinksIn.IsChecked())
	self.DrawGL()
	pass
    def OnHideLinksIn(self, buttonEvent):
	self.vwr.setDrawBackTo(hv.getSelected(), 0, self.DescendLinksIn.IsChecked())
	self.DrawGL()
	pass
    def OnShowLinksOut(self, buttonEvent):
	self.vwr.setDrawBackFrom(hv.getSelected(), 1, self.DescendLinksOut.IsChecked())
	self.DrawGL()
	pass
    def OnHideLinksOut(self, buttonEvent):
	self.vwr.setDrawBackFrom(hv.getSelected(), 0, self.DescendLinksOut.IsChecked())
	self.DrawGL()
	pass
    def OnDescendLinksIn(self, cmdEvent):
	node = self.currNode
	if node is None:
	    node = hv.getGraphCenter()
	linksIn = self.vwr.getIncomingCount(node)
	descend = self.DescendLinksIn.IsChecked()
	self.ShowLinksIn.Enable(descend or linksIn > 0)
	self.HideLinksIn.Enable(descend or linksIn > 0)
	self.DescendLinksIn.Enable(True)
	pass
    def OnDescendLinksOut(self, cmdEvent):
	node = self.currNode
	if node is None:
	    node = hv.getGraphCenter()
        linksOut = self.vwr.getOutgoingCount(node)
	descend = self.DescendLinksOut.IsChecked()
	self.ShowLinksOut.Enable(descend or linksOut > 0)
	self.HideLinksOut.Enable(descend or linksOut > 0)
	self.DescendLinksIn.Enable(True)
	pass
    
    ##
    # Combo boxes select between alternatives.
    def OnLabelsMode(self, cmdEvent):
	if self.vwr is None:
	    return
	which = self.LabelsMode.GetSelection()
	if which != -1:
	    self.vwr.setLabels(which)
	    self.DrawGL()
	    pass
	pass
    
    ##
    # Spin controls set integer state.
    def OnCountGenNode(self, spinEvent):
	##print "OnCountGenNode", self.vwr
	if self.vwr is None:
	    return
	self.vwr.setGenerationNodeLimit(self.CountGenNode.GetValue())
	self.DrawGL()
	pass
    def OnCountGenLink(self, spinEvent):
	##print "OnCountGenLink", self.vwr
	if self.vwr is None:
	    return
	self.vwr.setGenerationLinkLimit(self.CountGenLink.GetValue())
	self.DrawGL()
	pass

    ##
    # Actions for <ENTER> keypress in numeric fields.
    def OnCountGenNode_CHAR(self, keyEvent):
	key = keyEvent.GetKeyCode()
	if key == WXK_RETURN:
	    self.OnCountGenNode(keyEvent)
	    pass
	self.OnCharDigitsOnly(keyEvent)
	pass
    def OnCountGenLink_CHAR(self, keyEvent):
	key = keyEvent.GetKeyCode()
	if key == WXK_RETURN:
	    self.OnCountGenLink(keyEvent)
	    pass
	self.OnCharDigitsOnly(keyEvent)
	pass

    ##
    # Disable typing non-digits in numeric fields.
    def OnCharDigitsOnly(self, keyEvent):
	key = keyEvent.GetKeyCode()
	if ord('0') <= key <= ord('9') or key in (WXK_BACK, WXK_TAB) or key >= WXK_DELETE:
	    keyEvent.Skip()		# Skip actually means to process the event...
	    pass
	pass

    ##
    # Handle changes in the number of steps per second in animated moves.
    def OnAnimStepCount(self, scrollEvent):
	if self.vwr is None:
	    return
	# Convert from steps/second to fraction-of-a-second-per-step.
	stepsPerSec = self.AnimStepCount.GetValue()
	self.vwr.setGotoStepSize(1.0 / stepsPerSec)
        ##print "OnAnimStepCount", stepsPerSec, self.vwr.getGotoStepSize()
	pass
    
    ##
    # Menu items issue commands from the menu bar.
    def OnExit(self, cmdEvent):
	self.shutdown()
    def OnOpen(self, cmdEvent):
	self.app.openDialog.Show()
    def OnUsage(self, cmdEvent):
	self.app.usageDialog.Show()
    
    ##
    # Mouse click events.
    def OnClick(self, mouseEvent):
	if self.vwr is None:
	    return

	# Encode mouse button events for HypView.
	btnNum = -1
	
	# Left mouse button for X-Y motion of the hyperbolic center.
	if mouseEvent.LeftDown():
	    btnNum = 0
	    btnState = 0
	elif mouseEvent.LeftUp():
	    btnNum = 0
	    btnState = 1
	    pass
	
	# Middle button for rotation of the hyperbolic space.
	elif mouseEvent.MiddleDown():
	    btnNum = 1
	    btnState = 0
	elif mouseEvent.MiddleUp():
	    btnNum = 1
	    btnState = 1
	    pass
	
	# Left button with control or shift held down is also rotation.
	if btnNum == 0 and ( mouseEvent.ControlDown() or mouseEvent.ShiftDown() ):
	    btnNum = 1
	    pass
	
	# Handle mouse clicks in HypView.
	if btnNum != -1:
	    ##print "click", btnNum, btnState, mouseEvent.GetX(), mouseEvent.GetY()
	    self.vwr.mouse(btnNum, btnState, mouseEvent.GetX(), mouseEvent.GetY(), 0, 0)
	    self.vwr.redraw()
	    
	    # If a pick occurred, the current node name has changed.
	    self.SelectedNode(hv.getSelected())
	    pass
	pass
    
    ##
    # Mouse motion events in the HyperViewer canvas.
    def OnMove(self, mouseEvent):
	if self.vwr is None:
	    return

	# Hyperviewer calls motion when a mouse button is clicked "active"
	if mouseEvent.LeftIsDown() or mouseEvent.MiddleIsDown():
	    self.vwr.motion(mouseEvent.GetX(), mouseEvent.GetY(), 0, 0)
	else:
	    # "passive" mouse motion is when there is no button clicked.
	    ##print "passive", mouseEvent.GetX(), mouseEvent.GetY()
	    self.vwr.passive(mouseEvent.GetX(), mouseEvent.GetY(), 0, 0)
	    pass
	self.vwr.redraw()
	pass
    
    ##
    # Resizing for the splitter window.
    def OnResizeWindow(self, sizeEvent):
	# Keep the width of the controls panel in the splitter constant.
	self.window_1.SetSashPosition(self.window_1.GetSize().width - self.controlsWidth)
	pass

    def OnSashChanged(self, splitterEvent):
	# Remember an intentional change in the controls panel width.
	self.controlsWidth = self.window_1.GetSize().width - splitterEvent.GetSashPosition()
	pass

    ##
    # Resizing for the HyperViewer canvas.
    def OnResizeCanvas(self, sizeEvent):
	# Tie the size of the canvas to the panel it's in.
	size = self.panel_1.GetSize()
	# We get two resize events: one with a width of 20.  Ignore it.
	if size.width >= 20:
	    self.hypView.SetSize(size)

	    # Tell HyperViewer about the change.
	    if self.vwr:
		self.vwr.reshape(size.width, size.height)
	pass
    
    ##
    # Other events generated by the event toploop and window system interface.
    def OnIdle(self, idleEvent):
	if self.vwr:
	    self.vwr.idle()
	    pass
	###idleEvent.RequestMore()
	pass
    
    def OnPaint(self, paintEvent):
	if self.vwr:
	    self.vwr.redraw()
	    pass
	pass
    pass

class hvOpen(OpenDialogUI):
    ##
    # Open dialog frame initialization.
    def __init__(self, *args, **kwds):
	# Set up the wxGlade part.
	OpenDialogUI.__init__(self, *args, **kwds)
	    
	EVT_BUTTON(self.OpenFile, -1, self.OnOpenFile)
	EVT_TEXT_ENTER(self.FileToOpen, -1, self.OnOpenFile)

	EVT_BUTTON(self.OpenExperiment, -1, self.OnOpenExperiment)
	EVT_TEXT_ENTER(self.LoginName, -1, self.OnLoginName)
	EVT_TEXT_ENTER(self.ProjectName, -1, self.OnOpenExperiment)
	EVT_TEXT_ENTER(self.ExperimentName, -1, self.OnOpenExperiment)
	EVT_TEXT_ENTER(self.ExperimentRoot, -1, self.OnOpenExperiment)

	pass
    
    ##
    # Get topology data from a file.
    def OnOpenFile(self, cmdEvent):
	file = self.FileToOpen.GetLineText(0)
	if file == "":
	    msg = "Enter a file path."
	else:
	    msg = 'Reading topology file "%s".' % file
	print msg
	self.FileMsg.SetLabel(msg)
	self.Refresh()
	self.Update()
	if file == "":
	    return
	
	if self.app.frame.ReadTopFile("wxHyperViewer", file):
	    self.Hide();		# Success.
	    self.FileMsg.SetLabel(" ")
	else:
	    fileError = "Could not open " + file
	    self.FileMsg.SetLabel(fileError)
	    pass
	pass
    
    ##
    # Change the SSH login name.
    def OnLoginName(self, cmdEvent):
        exptToHv.login_id = self.LoginName.GetLineText(0)
        pass
    
    ##
    # Get topology data for an experiment from the database via XML-RPC.
    def OnOpenExperiment(self, cmdEvent):
	project = self.ProjectName.GetLineText(0)
	experiment = self.ExperimentName.GetLineText(0)
	root = self.ExperimentRoot.GetLineText(0)

	if project == "" or experiment == "":
	    msg = "Enter a project name and experiment."
	else:
	    msg = 'Getting experiment %s/%s.' % (project, experiment)
	    pass
	print msg
	self.ExperimentMsg.SetLabel(msg)
	self.Refresh()
	self.Update()
	if project == "" or experiment == "":
	    return
	
	hypfile = exptToHv.getExperiment(project, experiment, root)
	if type(hypfile) is types.ListType:
	    exptError = '%s %s\n%s' % tuple(hypfile[1:4])
	    print exptError
	    self.ExperimentMsg.SetLabel(exptError)

	elif self.app.frame.ReadTopFile("wxHyperViewer", hypfile):
	    self.Hide();		# Success.
	    self.ExperimentMsg.SetLabel(" ")
	else:
	    fileError = "Could not open " + hypfile
	    print fileError
	    self.ExperimentMsg.SetLabel(fileError)
	    pass
	pass
    
    pass

app = hvApp(0)		# Create the wxPython application.
app.MainLoop()		# Handle events.
