/*
 * Copyright (c) 2006 University of Utah and the Flux Group.
 * 
 * {{{EMULAB-LICENSE
 * 
 * This file is part of the Emulab network testbed software.
 * 
 * This file is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or (at
 * your option) any later version.
 * 
 * This file is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public
 * License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this file.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * }}}
 */

import java.util.*;
import java.io.*;
import java.util.regex.*;

/**
 *
 * Parses spec files generated by ncc, the nesc parser.  These specs are dumped
 * out from mig separately from the generated msg stub file.  We parse them
 * so as to ascertain structure/array layout for each msg structure; this info
 * allows more effective organization of database packet tables.
 * 
 *   if (type_complex(t))
 *    {
 *    printf("C");
 *    t = make_base_type(t);
 *  }
 *
 * Enums treated as ints for now 
 *  if (type_integer(t))
 *  if (type_unsigned(t))
 *    printf("U");
 *  else
 *    printf("I");
 *else if (type_float(t))
 *  printf("F");
 *else if (type_double(t))
 *  printf("D");
 *else if (type_long_double(t))
 *  printf("LD");
 *else if (type_union(t))
 *  printf("AU");
 *else if (type_struct(t))
 *  printf("AS");
 *else if (type_pointer(t))
 *  printf("U");
 *
 */
public class NCCSpecParser {

    public static void main(String[] args) throws Exception {
	SpecData sd = NCCSpecParser.parseSpecFile(new File(args[0]));
	sd.getRoot().print(0);
    }

    protected NCCSpecParser() { }

    private static void debug(int level,String msg) {
	MoteLogger.globalDebug(level,"NCCSpecParser: " + msg);
    }

    private static void error(String msg) {
	MoteLogger.globalError("NCCSpecParser: " + msg);
    }

    public static SpecData parseSpecFile(File specFile) throws Exception {
	ArrayList lines = new ArrayList();
	String specLines[] = null;

	if (specFile.canRead()) {
	    // read it in...
	    BufferedReader br = null;
	    String line = null;
	    try {
		int j = 0;
		br = new BufferedReader(new FileReader(specFile));
		while ((line = br.readLine()) != null) {
		    lines.add(j,line);
		    ++j;
		}
		
		specLines = new String[lines.size()];
		for (int i = 0; i < specLines.length; ++i) {
		    specLines[i] = (String)lines.get(i);
		}
	    }
	    catch (Exception e) {
		specLines = null;
		error("problem reading file " + specFile);
		e.printStackTrace();
	    }
	}
	else {
	    throw new Exception("could not read file " + specFile);
	}

	return parseSpec(specLines);
    }

    public static SpecData parseSpec(String[] lines) throws Exception {

	FieldInfo root = new FieldInfo();
	String specName = null;
	int rootType = FieldInfo.TYPE_UNKNOWN;
	Stack stack = new Stack();
	FieldInfo currentElm = root;

	for (int i = 0; i < lines.length; ++i) {
	    if (lines[i].equals("")) {
		continue;
	    }

	    String[] s = lines[i].split("[ \t]+");

	    int firstNonEmptyIdx = -1;
	    if (s != null && s.length > 1) {
		int idx = 0;
		while (idx < s.length) {
		    if (!s[idx].equals("")) {
			firstNonEmptyIdx = idx;
			break;
		    }
		    ++idx;
		}

		if (idx < s.length-1) {
		    String tmp[] = new String[s.length - firstNonEmptyIdx];
		    System.arraycopy(s,firstNonEmptyIdx,tmp,0,tmp.length);
		    s = tmp;
		}
	    }
	    else {
		continue;
	    }

	    // pretty basic here.... there's not a lot of possibilities:
	    if (s.length == 2) {
		// probably we are closing an aggregate type:
		if (s[1].equals("AX")) {
		    String fieldName = s[0];
		    if (!stack.empty()) {
			String top = (String)stack.peek();
			if (!top.equals(fieldName)) {
			    throw new Exception("end of aggregate field for " +
						"field " + fieldName + " " +
						"does not match expected " +
						"field " + top + " at " +
						"line " + (i+1));
			}
			else {
			    //debug(1,"finished complex type " + fieldName);
			    stack.pop();
			    // move back up an element:
			    currentElm = currentElm.getParent();
			}
		    }
		    else {
			throw new Exception("end of aggregate field for " +
					    "field " + fieldName + ", but " +
					    "no corresponding match at line " +
					    (i+1));
		    }
		}
		else {
		    throw new Exception("unrecognized spec, line " + (i+1));
		}
	    }
	    else if (s.length == 4) {
		
		if (s[0].equals("struct")) {
		    specName = s[1];
		    rootType = FieldInfo.TYPE_STRUCT;
		}
		else if (s[0].equals("union")) {
		    specName = s[1];
		    rootType = FieldInfo.TYPE_UNION;
		}
		else {
		    FieldInfo nF = new FieldInfo();
		    nF.parent = currentElm;

		    nF.fullName = s[0];
		    String bF[] = s[0].split("\\.");
		    if (bF != null && bF.length > 1) {
			nF.name = bF[bF.length-1];
		    }
		    else {
			nF.name = s[0];
		    }
		    
		    String typeName = s[1];

		    // figure out what this field is:
		    String arrayIdxStr = "\\[(\\d)+\\]";
		    Pattern arrayIdx = Pattern.compile(arrayIdxStr);
		    String typeStr = "(U|I|F|D|LD|AU|AS)";
		    Pattern type = Pattern.compile(typeStr);
		    
		    int lastBracketIdx = -1;

		    if ((lastBracketIdx = typeName.lastIndexOf("]")) != -1) {
	
			Matcher m = arrayIdx.matcher(typeName);
			// find the dimensions:
			int[] dims = new int[256];
			int j = 0;
			while (m.find() && j < dims.length) {
			    dims[j] = Integer.parseInt(m.group(1));
			    ++j;
			}
			
			if (j == dims.length) {
			    throw new Exception("more than 256 dimensions!");
			}

			nF.dim = new int[j];
			System.arraycopy(dims,0,nF.dim,0,j);

		    }
		    else {
			lastBracketIdx = 0;
		    }

		    // now get the type:
		    Matcher m = type.matcher(typeName.substring(lastBracketIdx,
								typeName.length()));
		    if (m.find()) {
			nF.type = FieldInfo.getTypeForSpecString(m.group(1));
			if (nF.type == FieldInfo.TYPE_UNKNOWN) {
			    throw new Exception("unrecognized type " +
						typeName + ", line " + (i+1));
			}
		    }
		    else {
			throw new Exception("unrecognized type " + typeName +
					    ", line " + (i+1));
		    }

		    // see if we have a parent...
		    if (currentElm != null) {
			currentElm.addChild(nF);
		    }
		    
		    // see if we will have children for the new field:
		    if (nF.type == FieldInfo.TYPE_STRUCT 
			|| nF.type == FieldInfo.TYPE_UNION) {

			stack.push(nF.fullName);
			currentElm = nF;
		    }

		}
	    }
	    else {
		for (int k = 0; k < s.length; ++k) {
		    System.out.print("'" + s[k] + "' ");
		}
		System.out.println();
		throw new Exception("unknown field format, line " + (i+1));
	    }
	}

	return new SpecData(specName,rootType,root);

    }

}

