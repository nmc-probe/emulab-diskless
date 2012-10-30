#!/usr/bin/env python
# generated by wxGlade 0.3.1 on Fri May 14 10:24:08 2004
#
# Copyright (c) 2004 University of Utah and the Flux Group.
# 
# {{{EMULAB-LICENSE
# 
# This file is part of the Emulab network testbed software.
# 
# This file is free software: you can redistribute it and/or modify it
# under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or (at
# your option) any later version.
# 
# This file is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
# FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public
# License for more details.
# 
# You should have received a copy of the GNU Affero General Public License
# along with this file.  If not, see <http://www.gnu.org/licenses/>.
# 
# }}}
#
from wxPython.wx import *
from wxPython.glcanvas import *

class hvGLCanvas(wxGLCanvas):
    def __init__(self, *args, **kwds):
        ## The PyOpenGL canvas initialization is broken.
        ## The following doesn't yet work on Windows, although
        ## it is needed for picking, and seg-faults on FreeBSD.
        ##kwds["attribList"] = [WX_GL_DOUBLEBUFFER, WX_GL_RGBA,
        ##                      WX_GL_DEPTH_SIZE, 32, 0]
        wxGLCanvas.__init__(self, *args, **kwds)
        pass
    pass

class UsageDialogUI(wxDialog):
    def __init__(self, *args, **kwds):
        # begin wxGlade: UsageDialogUI.__init__
        kwds["style"] = wxDEFAULT_DIALOG_STYLE
        wxDialog.__init__(self, *args, **kwds)
        self.Usage = wxStaticText(self, -1, "\n  HyperViewer mouse usage:\n  -----------------------------------\n\n  Objects close to the center of the sphere are largest.  \n\n  Left mouse drag: X,Y movement in hyperbolic space.  \n\n  Control- or Shift- Left mouse, or Middle mouse, drag:\n  Rotate the hyperbolic space.  \n\n  Pickable nodes under the cursor turn bright  green.    \n  Left-click to bring them to the center.  \n\n")

        self.__set_properties()
        self.__do_layout()
        # end wxGlade

    def __set_properties(self):
        # begin wxGlade: UsageDialogUI.__set_properties
        self.SetTitle("wxHyperViewer usage")
        # end wxGlade

    def __do_layout(self):
        # begin wxGlade: UsageDialogUI.__do_layout
        sizer_5 = wxBoxSizer(wxVERTICAL)
        sizer_5.Add(self.Usage, 0, 0, 10)
        sizer_5.Add(20, 1, 0, 0, 0)
        self.SetAutoLayout(1)
        self.SetSizer(sizer_5)
        sizer_5.Fit(self)
        sizer_5.SetSizeHints(self)
        self.Layout()
        # end wxGlade

# end of class UsageDialogUI


class OpenDialogUI(wxDialog):
    def __init__(self, *args, **kwds):
        # begin wxGlade: OpenDialogUI.__init__
        kwds["style"] = wxCAPTION|wxRESIZE_BORDER|wxTHICK_FRAME
        wxDialog.__init__(self, *args, **kwds)
        self.FileToOpen = wxTextCtrl(self, -1, "", style=wxTE_PROCESS_ENTER)
        self.OpenFile = wxButton(self, -1, "Open Data File")
        self.FileMsg = wxStaticText(self, -1, "")
        self.label_3 = wxStaticText(self, -1, "SSH login name:", style=wxALIGN_RIGHT)
        self.LoginName = wxTextCtrl(self, -1, "", style=wxTE_PROCESS_ENTER)
        self.label_2 = wxStaticText(self, -1, "Project name:", style=wxALIGN_RIGHT)
        self.ProjectName = wxTextCtrl(self, -1, "", style=wxTE_PROCESS_ENTER)
        self.label_7 = wxStaticText(self, -1, "Experiment name:")
        self.ExperimentName = wxTextCtrl(self, -1, "", style=wxTE_PROCESS_ENTER)
        self.label_8 = wxStaticText(self, -1, "Root (optional):")
        self.ExperimentRoot = wxTextCtrl(self, -1, "", style=wxTE_PROCESS_ENTER)
        self.OpenExperiment = wxButton(self, -1, "Retrieve experiment")
        self.ExperimentMsg = wxStaticText(self, -1, " \n ")

        self.__set_properties()
        self.__do_layout()
        # end wxGlade

    def __set_properties(self):
        # begin wxGlade: OpenDialogUI.__set_properties
        self.SetTitle("Open HyperViewer Data")
        self.SetSize((430, 372))
        self.FileToOpen.SetFocus()
        # end wxGlade

    def __do_layout(self):
        # begin wxGlade: OpenDialogUI.__do_layout
        sizer_4 = wxBoxSizer(wxVERTICAL)
        ExperimentOpening = wxStaticBoxSizer(wxStaticBox(self, -1, "Experiment to retrieve:"), wxVERTICAL)
        grid_sizer_1 = wxFlexGridSizer(4, 2, 10, 10)
        FileOpening = wxStaticBoxSizer(wxStaticBox(self, -1, "File for HyperViewer data:"), wxVERTICAL)
        FileOpening.Add(self.FileToOpen, 0, wxTOP|wxEXPAND, 5)
        FileOpening.Add(self.OpenFile, 0, wxTOP|wxALIGN_CENTER_HORIZONTAL, 10)
        FileOpening.Add(self.FileMsg, 0, 0, 0)
        sizer_4.Add(FileOpening, 0, wxALL|wxEXPAND, 10)
        grid_sizer_1.Add(self.label_3, 0, wxALIGN_CENTER_VERTICAL, 0)
        grid_sizer_1.Add(self.LoginName, 0, wxTOP|wxEXPAND, 5)
        grid_sizer_1.Add(self.label_2, 0, wxALIGN_CENTER_VERTICAL, 0)
        grid_sizer_1.Add(self.ProjectName, 0, wxEXPAND, 0)
        grid_sizer_1.Add(self.label_7, 0, wxALIGN_CENTER_VERTICAL, 0)
        grid_sizer_1.Add(self.ExperimentName, 0, wxEXPAND, 0)
        grid_sizer_1.Add(self.label_8, 0, wxALIGN_CENTER_VERTICAL, 0)
        grid_sizer_1.Add(self.ExperimentRoot, 0, wxEXPAND, 0)
        grid_sizer_1.AddGrowableCol(1)
        ExperimentOpening.Add(grid_sizer_1, 0, wxEXPAND, 0)
        ExperimentOpening.Add(self.OpenExperiment, 0, wxTOP|wxALIGN_CENTER_HORIZONTAL, 10)
        ExperimentOpening.Add(self.ExperimentMsg, 0, 0, 0)
        sizer_4.Add(ExperimentOpening, 0, wxALL|wxEXPAND, 10)
        self.SetAutoLayout(1)
        self.SetSizer(sizer_4)
        self.Layout()
        # end wxGlade

# end of class OpenDialogUI


class hvFrameUI(wxFrame):
    def __init__(self, *args, **kwds):
        # begin wxGlade: hvFrameUI.__init__
        kwds["style"] = wxDEFAULT_FRAME_STYLE
        wxFrame.__init__(self, *args, **kwds)
        self.window_1 = wxSplitterWindow(self, -1)
        self.Controls = wxPanel(self.window_1, -1)
        self.panel_1 = wxPanel(self.window_1, -1)
        
        # Menu Bar
        self.Menu = wxMenuBar()
        self.SetMenuBar(self.Menu)
        wxglade_tmp_menu = wxMenu()
        wxglade_tmp_menu.Append(1, "&Open...\tCtrl+O", "", wxITEM_NORMAL)
        wxglade_tmp_menu.Append(2, "E&xit\tCtrl+Q", "", wxITEM_NORMAL)
        self.Menu.Append(wxglade_tmp_menu, "&File")
        wxglade_tmp_menu = wxMenu()
        wxglade_tmp_menu.Append(3, "&Usage\tCtrl+H", "", wxITEM_NORMAL)
        self.Menu.Append(wxglade_tmp_menu, "&Help")
        # Menu Bar end
        self.hypView = hvGLCanvas(self.panel_1, -1)
        self.GoToTop = wxButton(self.Controls, -1, "go to top")
        self.LabelNodeName = wxStaticText(self.Controls, -1, "Node name:")
        self.NodeName = wxTextCtrl(self.Controls, -1, "", style=wxTE_PROCESS_ENTER)
        self.LabelChildCount = wxStaticText(self.Controls, -1, "Child count:  ")
        self.ChildCount = wxStaticText(self.Controls, -1, " 0  ")
        self.LabelLinksIn = wxStaticText(self.Controls, -1, "Non-tree Links in:   0")
        self.ShowLinksIn = wxButton(self.Controls, -1, "show")
        self.HideLinksIn = wxButton(self.Controls, -1, "hide")
        self.DescendLinksIn = wxCheckBox(self.Controls, -1, "descend")
        self.LabelLinksOut = wxStaticText(self.Controls, -1, "Non-tree Links out:  0")
        self.ShowLinksOut = wxButton(self.Controls, -1, "show")
        self.HideLinksOut = wxButton(self.Controls, -1, "hide")
        self.DescendLinksOut = wxCheckBox(self.Controls, -1, "descend")
        self.static_line_1 = wxStaticLine(self.Controls, -1)
        self.DrawSphere = wxCheckBox(self.Controls, -1, "Draw sphere")
        self.DrawNodes = wxCheckBox(self.Controls, -1, "Draw nodes")
        self.DrawLinks = wxCheckBox(self.Controls, -1, "Draw links")
        self.KeepAspect = wxCheckBox(self.Controls, -1, "Keep aspect")
        self.LabelToRight = wxCheckBox(self.Controls, -1, "Label to right")
        self.LabelsMode = wxChoice(self.Controls, -1, choices=["None", "Short", "Long"])
        self.label_3 = wxStaticText(self.Controls, -1, "  Labels")
        self.CountGenNode = wxSpinCtrl(self.Controls, -1, "30", min=1, max=30)
        self.label_4 = wxStaticText(self.Controls, -1, "  Node depth")
        self.CountGenLink = wxSpinCtrl(self.Controls, -1, "30", min=1, max=30)
        self.label_5 = wxStaticText(self.Controls, -1, "  Link depth")
        self.AnimStepCount = wxSlider(self.Controls, -1, 12, 1, 100, style=wxSL_HORIZONTAL|wxSL_LABELS)
        self.label_6 = wxStaticText(self.Controls, -1, "    Animation frames")
        self.static_line_2 = wxStaticLine(self.Controls, -1)
        self.HelpButton = wxButton(self.Controls, -1, "Help")

        self.__set_properties()
        self.__do_layout()
        # end wxGlade

    def __set_properties(self):
        # begin wxGlade: hvFrameUI.__set_properties
        self.SetTitle("wxHyperViewer")
        self.SetSize((760, 651))
        self.ShowLinksIn.Enable(0)
        self.HideLinksIn.Enable(0)
        self.DescendLinksIn.SetValue(1)
        self.ShowLinksOut.Enable(0)
        self.HideLinksOut.Enable(0)
        self.DescendLinksOut.SetValue(1)
        self.DrawSphere.SetValue(1)
        self.DrawNodes.SetValue(1)
        self.DrawLinks.SetValue(1)
        self.KeepAspect.SetValue(1)
        self.LabelsMode.SetSize((70, 25))
        self.LabelsMode.SetSelection(2)
        self.CountGenNode.SetSize((70, 25))
        self.CountGenNode.SetToolTipString("Generation Node Limit")
        self.label_4.SetToolTipString("Generation Link Limit")
        self.CountGenLink.SetSize((70, 25))
        self.CountGenLink.SetToolTipString("Generation Link Limit")
        self.label_5.SetToolTipString("Generation Link Limit")
        self.AnimStepCount.SetSize((150, 40))
        self.AnimStepCount.SetToolTipString("Number of frames per second in animated moves")
        self.label_6.SetToolTipString("Number of frames per animation")
        self.window_1.SetSize((760, 619))
        self.window_1.SplitVertically(self.panel_1, self.Controls, 560)
        # end wxGlade
        self.window_1.SetMinimumPaneSize(21) # Keep the controls or viewer from disappearing.

    def __do_layout(self):
        # begin wxGlade: hvFrameUI.__do_layout
        sizer_1 = wxBoxSizer(wxVERTICAL)
        sizer_2 = wxBoxSizer(wxVERTICAL)
        Modes = wxBoxSizer(wxVERTICAL)
        AnimSteps = wxBoxSizer(wxVERTICAL)
        GenLinkLimit = wxBoxSizer(wxHORIZONTAL)
        GenNodeLimit = wxBoxSizer(wxHORIZONTAL)
        Labels = wxBoxSizer(wxHORIZONTAL)
        Nodes = wxBoxSizer(wxVERTICAL)
        sizer_9 = wxBoxSizer(wxHORIZONTAL)
        sizer_8 = wxBoxSizer(wxHORIZONTAL)
        sizer_7 = wxBoxSizer(wxHORIZONTAL)
        sizer_6 = wxBoxSizer(wxHORIZONTAL)
        sizer_3 = wxBoxSizer(wxVERTICAL)
        sizer_3.Add(self.hypView, 99, wxEXPAND, 0)
        self.panel_1.SetAutoLayout(1)
        self.panel_1.SetSizer(sizer_3)
        sizer_3.Fit(self.panel_1)
        sizer_3.SetSizeHints(self.panel_1)
        Nodes.Add(self.GoToTop, 0, 0, 15)
        sizer_6.Add(self.LabelNodeName, 0, 0, 10)
        sizer_6.Add(self.NodeName, 1, wxLEFT|wxEXPAND, 5)
        Nodes.Add(sizer_6, 1, wxEXPAND, 0)
        sizer_7.Add(self.LabelChildCount, 0, 0, 10)
        sizer_7.Add(self.ChildCount, 0, 0, 0)
        Nodes.Add(sizer_7, 1, wxEXPAND, 0)
        Nodes.Add(self.LabelLinksIn, 0, 0, 10)
        sizer_8.Add(self.ShowLinksIn, 0, wxLEFT, 15)
        sizer_8.Add(self.HideLinksIn, 0, 0, 0)
        Nodes.Add(sizer_8, 1, 0, 0)
        Nodes.Add(self.DescendLinksIn, 0, wxALIGN_CENTER_HORIZONTAL, 0)
        Nodes.Add(self.LabelLinksOut, 0, wxTOP, 10)
        sizer_9.Add(self.ShowLinksOut, 0, wxLEFT, 15)
        sizer_9.Add(self.HideLinksOut, 0, 0, 0)
        Nodes.Add(sizer_9, 1, 0, 0)
        Nodes.Add(self.DescendLinksOut, 0, wxALIGN_CENTER_HORIZONTAL, 0)
        sizer_2.Add(Nodes, 0, wxALL|wxEXPAND, 10)
        sizer_2.Add(self.static_line_1, 0, wxEXPAND, 10)
        Modes.Add(self.DrawSphere, 0, 0, 10)
        Modes.Add(self.DrawNodes, 0, 0, 0)
        Modes.Add(self.DrawLinks, 0, 0, 0)
        Modes.Add(self.KeepAspect, 0, 0, 0)
        Modes.Add(self.LabelToRight, 0, wxTOP, 10)
        Labels.Add(self.LabelsMode, 0, 0, 0)
        Labels.Add(self.label_3, 0, 0, 0)
        Modes.Add(Labels, 0, 0, 0)
        GenNodeLimit.Add(self.CountGenNode, 0, 0, 10)
        GenNodeLimit.Add(self.label_4, 0, 0, 0)
        Modes.Add(GenNodeLimit, 0, wxTOP, 15)
        GenLinkLimit.Add(self.CountGenLink, 0, 0, 0)
        GenLinkLimit.Add(self.label_5, 0, 0, 0)
        Modes.Add(GenLinkLimit, 0, 0, 0)
        AnimSteps.Add(self.AnimStepCount, 0, wxBOTTOM, 0)
        AnimSteps.Add(self.label_6, 0, 0, 0)
        Modes.Add(AnimSteps, 0, wxTOP, 10)
        sizer_2.Add(Modes, 0, wxALL, 10)
        sizer_2.Add(self.static_line_2, 0, wxTOP|wxBOTTOM|wxEXPAND, 10)
        sizer_2.Add(self.HelpButton, 0, wxALIGN_CENTER_HORIZONTAL|wxALIGN_CENTER_VERTICAL, 10)
        self.Controls.SetAutoLayout(1)
        self.Controls.SetSizer(sizer_2)
        sizer_2.Fit(self.Controls)
        sizer_2.SetSizeHints(self.Controls)
        sizer_1.Add(self.window_1, 1, wxEXPAND, 0)
        self.SetAutoLayout(1)
        self.SetSizer(sizer_1)
        self.Layout()
        # end wxGlade

# end of class hvFrameUI


