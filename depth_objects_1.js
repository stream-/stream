var depthObjects = {

    poolKeyBoundingBox: null,
    errorCounter: [],
    callFunction: null,
    startShow: function() {
        if (VideoSettings.Resolution.width == undefined) {
            alert('Firstly VideoSettings.Resolution.width should be initialized');
            return;
        }
        //VideoStream.startTofVideo(depthObjects.getOnvif);
        depthObjects.getOnvif();
    },

    getOnvif: function () {
        if (depthObjects.callFunction)
            depthObjects.callFunction();
        var xml;
        var req = XmlHttpRequest();
        req.open('GET', '/source/bin/ecv?get_onvif', false);
        req.onreadystatechange = function() {
            if (typeof mode === "undefined" || !mode.draw && !mode.select) {

                if (req.status == 200 && req.responseXML)
                    xml = req.responseXML;
                else {
                    if (req.status == 408 || !req.status || req.status == 200) {
                        // nothing to draw - simply retry 
                        if (console.log) {
                            //console.log('getOnvif(): req.status='+req.status + " (" +req.statusText + ") -> RETRYING...");
                        }
                        
                        setTimeout(function() {depthObjects.getOnvif();}, (req.status == 408) ? 1000 : 30);
                        return;
                    }
                    if (errorCounter.length < 5) {
                        errorCounter.push(req.status + req.responseText);
                        if (console) console.error(req);
                        return;
                    }
                    if (console) console.error(req);
                    errorCounter.push(req.status + req.responseText);
                    alert('Some errors are occured during onvif requests. Error list: \n' + errorCounter.join('\n'));
                    depthObjects.poolKeyBoundingBox = draw.pool.deleteObject(depthObjects.poolKeyBoundingBox);
                    console.log('delete redraw');
                    draw.redraw();
                    return;
                }
                errorCounter = [];
                if (typeof(draw) !== 'undefined') {
                    depthObjects.poolKeyBoundingBox = draw.pool.deleteObject(depthObjects.poolKeyBoundingBox);
                }

                var depth = xml.documentElement.getElementsByTagName('DepthData');
                if (depth && depth.length > 0) {
                    var data = depth[0].childNodes[0].firstChild.textContent;
                    //console.log(atob(data.replace(/[ \n]/g, '')))
                    var arr = /*new Uint8Array(*/atob(data.replace(/[ \n]/g, ''));
                    var i = 0;
                    for (var idx = 0; idx < arr.length; idx++) {
                        var gray = arr.charCodeAt(idx);
                        VideoStream.tof_data.data[i++] = gray;
                        VideoStream.tof_data.data[i++] = gray;
                        VideoStream.tof_data.data[i++] = gray;
                        VideoStream.tof_data.data[i++] = 255; // alpha
                    }
                    if (arr.length) {
                        VideoStream.tof_ctx.putImageData(VideoStream.tof_data, 0, 0);
                        VideoStream.destination.src = VideoStream.tof.toDataURL("image/gif");
                    }
                } else {
                    if(VideoStream.paused) {
                        VideoStream.do_continue();  //VideoStream.get_frame();
                        
                    }
                }

                var objects = xml.documentElement.getElementsByTagName('Object');
                if (objects) {
                    var boxes = [];
                    var trs = TofHardwareSettings.TOF_to_RGB_sync;
                    /*for (var i = 0; i < objects.length; i++) {
                        //console.log(objects[i].getElementsByTagName('Shape'));
                        // <Object value="297 32 189 102 256 170 Human 1 4.91"/>
                        if (typeof(objects[i]) !== 'undefined' && typeof(objects[i].childNodes[0]) !== 'undefined') {
                            var bb = objects[i].childNodes[0].getElementsByTagName('Shape')[0].childNodes[0].attributes;
                            //console.log(bb);
                        } else {
                            //console.log('Can\'t find object\'s child in depth on line 78');
                            //console.log(objects[i].getAttribute('value'));
                            var bbS = /[\d+\s]+/g.exec(objects[i].getAttribute('value'));
                            //console.log(bbS, bbS[0]);
                            
                            var sBb = bbS[0];
                        }
                        //var bb = objects[i].childNodes[0].getElementsByTagName('Shape')[0].childNodes[0].attributes;
                        if (bb) {
                            var left = (parseInt(bb.getNamedItem('left').value) - trs.OffsetX.init) / trs.ScaleX.init;
                            var top = (parseInt(bb.getNamedItem('top').value) - trs.OffsetY.init) / trs.ScaleY.init;
                            var right = (parseInt(bb.getNamedItem('right').value) - trs.OffsetX.init) / trs.ScaleX.init;
                            var bottom = (parseInt(bb.getNamedItem('bottom').value) - trs.OffsetY.init) / trs.ScaleY.init;
                            if (draw.videoScaleX) {
                                boxes.push({
                                    l: Math.floor(left * draw.sensorScaleX),
                                    t: Math.floor(top * draw.sensorScaleY),
                                    r: Math.floor(right * draw.sensorScaleX),
                                    b: Math.floor(bottom * draw.sensorScaleY)
                                });
                            } else
                                boxes.push({
                                    l: Math.floor(left * draw.videoScale),
                                    t: Math.floor(top * draw.videoScale),
                                    r: Math.floor(right * draw.videoScale),
                                    b: Math.floor(bottom * draw.videoScale)
                                });
                        } else {
                            var aBb = sBb.split(' ');
                            //console.log(aBb);
                            // ["235", "1222", "258", "1362", "394", "104", ""] 
                            var left = (parseInt(aBb[1]) - trs.OffsetX.init) / trs.ScaleX.init;
                            var top = (parseInt(aBb[2]) - trs.OffsetY.init) / trs.ScaleY.init;
                            var right = (parseInt(aBb[3]) - trs.OffsetX.init) / trs.ScaleX.init;
                            var bottom = (parseInt(aBb[4]) - trs.OffsetY.init) / trs.ScaleY.init;
                            if (draw.videoScaleX) {
                                boxes.push({
                                    l: Math.floor(left * draw.sensorScaleX),
                                    t: Math.floor(top * draw.sensorScaleY),
                                    r: Math.floor(right * draw.sensorScaleX),
                                    b: Math.floor(bottom * draw.sensorScaleY)
                                });
                            } else
                                boxes.push({
                                    l: Math.floor(left * draw.videoScale),
                                    t: Math.floor(top * draw.videoScale),
                                    r: Math.floor(right * draw.videoScale),
                                    b: Math.floor(bottom * draw.videoScale)
                                });
                        }
                    }*/
                    if (boxes.length) {
                        depthObjects.poolKeyBoundingBox = draw.pool.addObject(
                            function() {
                                var da = draw.area;
                                da.save();
                                da.beginPath();
                                for (var i = 0; i < boxes.length; i++) {
                                    var b = boxes[i];
                                    da.moveTo(b.l, b.t);
                                    da.lineTo(b.r, b.t);
                                    da.lineTo(b.r, b.b);
                                    da.lineTo(b.l, b.b);
                                    da.lineTo(b.l, b.t);
                                }
                                da.strokeStyle = 'red';
                                da.lineWidth = 2;
                                da.stroke();
                                da.closePath();
                                da.restore();
                            }
                        );
                    }
                }

                if (typeof countingLinesList !== 'undefined') {
                    var counting = xml.documentElement.getElementsByTagName('Counting')[0];
                    if (counting) {
                        var lines = counting.childNodes;
                        if (lines != undefined && lines) {
                            for (var i = 0; i <= lines.length; i++) {
                                var line = lines[i];
                                if (line && line.nodeName == 'Line') {
                                    var la = line.attributes;
                                    if (la) {
                                        for (var j = 0; j <= CountingLines.length; j++) {
                                            var cl = CountingLines[j];
                                            if (la.getNamedItem('Id')) {
                                                if (cl && cl.name === la.getNamedItem('Id').value) {
                                                    var td = countingLinesList.getElementsByTagName('tr')[j+1].getElementsByTagName("td");
                                                    td[2].innerHTML = la.getNamedItem('Enters').value;
                                                    td[3].innerHTML = la.getNamedItem('Exits').value;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
             } else
                depthObjects.poolKeyBoundingBox = draw.pool.deleteObject(depthObjects.poolKeyBoundingBox);
            //if (/*!draw.areas &&*/ draw.areas.length === 0) {
                //draw.redraw();
            //}
            
            if (parseInt(sessionStorage.getItem('videoStop')) === 0 || sessionStorage.getItem('videoStop') === null) {
                setTimeout(function() {depthObjects.getOnvif();}, 30);
            }
        }
        req.send('');
    }
}
