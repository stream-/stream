var draw = {
    videoScale: 1,
    videoScaleX: 1,
    videoScaleY: 1,
    sensorScaleX: 1,
    sensorScaleY: 1,
    canvas: null, 
    area: null,
    disable: true,
    isStatusbarMessage: false,
    isButtonPressed: 0,
    pool: {
        objects: {},
        methodIndex: 0,
        addObject: function(obj, key) {
            if (key == undefined)
                key = 'o' + draw.pool.methodIndex++;
            draw.pool.objects[key] = obj;
            return key;
        },
        deleteObject: function(key) {
            if (key)
                delete draw.pool.objects[key];
            return null;
        }
    }, // this is pool of methods to draw some pool
    width: 0,
    height: 0,

    pointer: {
        blocked: true,
        cursor: 'none',
        x: 0,
        y: 0,
        interval: 3000,
        hInterval: null
    },
    point: {
        num: 0,
        x: 0,
        y: 0
    },

    redraw: function() {
        if (!draw.area)
            return false;
        draw.canvas.width = draw.canvas.width;

        //if (draw.disable)
            // return false;
        //draw.canvas.height = draw.height;
        //draw.area.clearRect(0, 0, draw.width, draw.height);
        for (var key in draw.pool.objects)
            draw.pool.objects[key]();
    },
    init: function() {
        if (this.area)
            return false;
        this.canvas = document.createElement('canvas');
        if (this.canvas.getContext && typeof(container) !== 'undefined') {
            //var container = document.getElementById('container');

            this.canvas.style.zIndex = 999;
            this.canvas.style.position = 'absolute';
            //this.canvas.style.left = this.video.offsetLeft + 'px';
            var sr = TofHardwareSettings.TOF_FoV.SensorRes;
            this.sensorScaleX = container.offsetWidth / sr.width;
            this.sensorScaleY = container.offsetHeight / sr.height;
            this.width = container.offsetWidth;
            this.height = container.offsetHeight;
            this.canvas.width = this.width;
            this.canvas.height = this.height;

            container.appendChild(this.canvas);

            this.area = this.canvas.getContext('2d');
            //this.area.translate(0.5, 0.5);
            this.area.lineWidth = 1;
            this.area.shadowBlur = 0;

            this.canvas.addEventListener(
                'mousedown',
                function(event) {
                    if (window.event) event = window.event;

                    draw.isButtonPressed = 1;   //event.button

                    var dp = draw.pointer;
                    dp.x = event.offsetX;
                    dp.y = event.offsetY;

                    if (Polygon.enabled)
                        Polygon.mousedown(event);
                }
            );

            this.canvas.addEventListener( // MOVE MOVE MOVE MOVE MOVE MOVE MOVE MOVE MOVE MOVE MOVE MOVE
                'mousemove',
                function(event) {
                    if (window.event) event = window.event;

                    if (draw.isButtonPressed == 1 && draw.isStatusbarMessage) {
                        draw.isStatusbarMessage = false;
                        StatusBar.hide();
                    }

                    if (Anchors.enabled && !Polygon.moving) {
                        if (Anchors.mousemove(event)) {
                            return;
                        }
                    }

                    if (Polygon.enabled) {
                        Polygon.mousemove(event);
                        if (Polygon.moving)
                            return;
                    }
                }
            );

            this.canvas.addEventListener(
                'mouseup',
                function(event) {
                    if (window.event) event = window.event;

                    draw.isButtonPressed = 0;   // event.button

                    if (Polygon.enabled)
                        Polygon.mouseup(event);

                    draw.redraw();
                }
            );
        }
    }
}

var Anchors = {
    radius: 3,
    enabled: false,
    initialized: false,
    color: '#9F023A',
    init: function() {
        if (Anchors.initialized)
            return;
        Anchors.initialized = true;
        draw.pool.addObject(Anchors.draw);
    },
    mousemove: function(event) {
        var fa = CvSoftwareSettings.FloorAnchors;
        if (!fa)
            return;

        var x1 = fa.x1 * draw.sensorScaleX;
        var y1 = fa.y1 * draw.sensorScaleY;
        var x2 = fa.x2 * draw.sensorScaleX;
        var y2 = fa.y2 * draw.sensorScaleY;
        var x3 = fa.x3 * draw.sensorScaleX;
        var y3 = fa.y3 * draw.sensorScaleY;

        var x = event.offsetX - draw.canvas.offsetLeft;
        var y = event.offsetY - draw.canvas.offsetTop;
        var r1 = Math.floor((Math.sqrt(Math.pow(x-x1,2) + Math.pow(y-y1,2))));
        var r2 = Math.floor((Math.sqrt(Math.pow(x-x2,2) + Math.pow(y-y2,2))));
        var r3 = Math.floor((Math.sqrt(Math.pow(x-x3,2) + Math.pow(y-y3,2))));

        var dp = draw.point;
        if (draw.isButtonPressed == 1) {
            if (!dp.num)
                return false;

            if (sbEnableInvalidRegion.isEnabled) {
                for (var fi = 0; fi < Math.PI*2; fi+=Math.PI/12) {
                    var xx = (10)*Math.cos(fi)+0.5;
                    var yy = (10)*Math.sin(fi)+0.5;
                    if (Polygon.withPoint(x+xx, y+yy)) {
                        if (!Polygon.enabled) {
                            Polygon.init();
                            Polygon.enabled = true;
                            draw.redraw();
                            setTimeout(function() {
                                Polygon.enabled = false;
                                draw.redraw();
                            }, 200);
                        }
                        return true;
                    }
                }
                if (anchorWithinRegion) {
                    anchorWithinRegion = false;
                    messagePanel.hide();
                }
            }

            if (dp.num == 1) {
                fa.x1 = Math.floor(x/draw.sensorScaleX + 1);
                fa.y1 = Math.floor(y/draw.sensorScaleY);
            } else
            if (dp.num == 2) {
                fa.x2 = Math.floor(x/draw.sensorScaleX + 1);
                fa.y2 = Math.floor(y/draw.sensorScaleY);
            } else
            if (dp.num == 3) {
                fa.x3 = Math.floor(x/draw.sensorScaleX + 1);
                fa.y3 = Math.floor(y/draw.sensorScaleY);
            }
            if (dp.num) {
                draw.redraw();
                //params_changed();
            }
        } else {
            var r = Anchors.radius * draw.sensorScaleY;
            dp.num = (r1 < r) ? 1 : ((r2 < r) ? 2 : ((r3 < r) ? 3 : 0));
            draw.canvas.style.cursor = (dp.num) ? 'pointer' : 'default';
        }
        return (dp.num) ? true : false;
    },

    draw: function () {
        if (!Anchors.enabled)
            return;
        //var scale = draw.videoScale;
        var r = Anchors.radius * draw.sensorScaleY;
        var fa = CvSoftwareSettings.FloorAnchors;
        if (!fa)
            return;
        var x1 = fa.x1 * draw.sensorScaleX;
        var y1 = fa.y1 * draw.sensorScaleY;
        var x2 = fa.x2 * draw.sensorScaleX;
        var y2 = fa.y2 * draw.sensorScaleY;
        var x3 = fa.x3 * draw.sensorScaleX;
        var y3 = fa.y3 * draw.sensorScaleY;
        var da = draw.area;
        da.save();
        da.beginPath();
        //da.font = '2pt Helvetica';
        //da.fillStyle = 'black';
        //da.strokeStyle = 'black';
        //da.textAlign = 'left';
        //da.textBaseline = "bottom";
        if (x1 != NaN && y1 != NaN && x1 > 0 && y1 > 0) {
            da.moveTo(x1 + 0.5, y1 + 0.5);
            da.arc(x1 + 0.5, y1 + 0.5, r, 0, 2 * Math.PI, false);
            //da.fillText("1", x1+1, y1-1);
        }
        if (x2 != NaN && y3 != NaN && x2 > 0 && y2 > 0) {
            da.moveTo(x2 + 0.5, y2 + 0.5);
            da.arc(x2 + 0.5, y2 + 0.5, r, 0, 2 * Math.PI, false);
            //da.fillText("2", x2+1, y2-1);
        }
        if (x3 != NaN && y3 != NaN && x3 > 0 && y3 > 0) {
            da.moveTo(x3 + 0.5, y3 + 0.5);
            da.arc(x3 + 0.5, y3 + 0.5, r, 0, 2 * Math.PI, false);
            //da.fillText("3", x3+1, y3-1);
        }
        da.globalAlpha = 1;
        da.fillStyle =
            da.strokeStyle = Anchors.color;
        da.fill();
        da.closePath();

        da.globalCompositeOperation = 'xor'; //'destination-over';
        da.beginPath();
        da.globalAlpha = 1;
        da.fillStyle = da.strokeStyle = 'white';

        if (x1 != NaN && y1 != NaN && x1 > 0 && y1 > 0) {
            da.moveTo(x1 + 0.5, y1 + 0.5);
            da.arc(x1 + 0.5, y1 + 0.5, 0.3, 0, 2 * Math.PI, false);
            //da.fillText("1", x1+1, y1-1);
        }
        if (x2 != NaN && y3 != NaN && x2 > 0 && y2 > 0) {
            da.moveTo(x2 + 0.5, y2 + 0.5);
            da.arc(x2 + 0.5, y2 + 0.5, 0.3, 0, 2 * Math.PI, false);
            //da.fillText("2", x2+1, y2-1);
        }
        if (x3 != NaN && y3 != NaN && x3 > 0 && y3 > 0) {
            da.moveTo(x3 + 0.5, y3 + 0.5);
            da.arc(x3 + 0.5, y3 + 0.5, 0.3, 0, 2 * Math.PI, false);
            //da.fillText("3", x3+1, y3-1);
        }
        da.fill();
        da.closePath();

        da.restore();
        //da.globalAlpha = 0;
        //da.lineWidth = 0;
        //da.stroke();
    }
}

var Polygon = {
    enabled: false,
    hInterval: null,
    initialized: false,
    invalid: false,
    color: '#6ed0f7',
    moving: false,
    fillPattern: null,
    lineWidth: 0.5,
    dir: -1,
    highlighted_node: {
        index: null,
        coord: {x0: 0, y0: 0, x1: 0, y1: 0},
        do_delete: false
    },

    init: function() {
        if (Polygon.initialized)
            return;
        Polygon.initialized = true;
        draw.pool.addObject(Polygon.draw);
        if (window.addEventListener) {
            clearAllPolygon.addEventListener('click', Polygon.doClearAllPolygon);
            cancelClearAll.addEventListener('click', Polygon.doCancelClearAll);
        } else {
            cancelClearAll.onchange = Polygon.doCancelClearAll;
            clearAllPolygon.onchange = Polygon.doClearAllPolygon;
        }
        //var patternImage = new Image();
        //patternImage.src = '';
        //Polygon.fillPattern = draw.area.createPattern(patternImage, "repeat");
    },

    doClearAllPolygon: function() {
        if (Polygon.highlighted_node.do_delete) {
            // reset polygon to default in this version
            CvSoftwareSettings.InvalidRegion.coord = [[20, 20], [20, 50], [50, 50], [50, 20], [20, 20]];
            Polygon.doCancelClearAll();
            //params_changed();
            invalidRegion.style.color = colorIfChanged;
            if (anchorWithinRegion) {
                anchorWithinRegion = false;
                messagePanel.hide();
            }
        }
    },
    doCancelClearAll: function() {
        draw.pool.deleteObject('polygon');
        Polygon.highlighted_node.do_delete = false;
        Polygon.highlighted_node.index = null;
        clearAllButtons.style.display = 'none';
        draw.redraw();
    },

    mousedown: function(event) {
        if (!Polygon.enabled)
            return;

        var dp = draw.pointer;
        dp.x = event.offsetX - draw.canvas.offsetLeft;
        dp.y = event.offsetY - draw.canvas.offsetTop;
        var dhn = Polygon.highlighted_node;
        var x;
        var y;
        if (dhn.index < 0) {
            x = (dhn.coord.x0 + dhn.coord.x1) / 2;
            y = (dhn.coord.y0 + dhn.coord.y1) / 2;
        } else {
            x = dhn.coord.x0;
            y = dhn.coord.y0;
        }
        var r = Math.floor(Math.sqrt(Math.pow(x-dp.x,2) + Math.pow(y-dp.y,2)));
        if (dhn.do_delete && r > 10) {
            draw.pool.deleteObject('delete' + Math.abs(dhn.index));
            draw.pool.deleteObject('polygon');
            if (clearAllButtons.style.display == 'block')
                clearAllButtons.style.display = 'none';
            dhn.do_delete = false;
            dhn.index = null;
        }
    },

    mousemove: function(event) {
        if (!Polygon.enabled)
            return;

        var x = event.offsetX - draw.canvas.offsetLeft;
        var y = event.offsetY - draw.canvas.offsetTop;
        var coord = CvSoftwareSettings.InvalidRegion.coord;
        var dhn = Polygon.highlighted_node;
        var dp = draw.pointer;
        if (draw.isButtonPressed == 1) {
            // left mouse button is pressed
            if (dhn.index != null) {
                invalidRegion.style.color = colorIfChanged;
                if (dhn.index < 0) {
                    // add new joint to center of selected segment
                    var i = -dhn.index;
                    CvSoftwareSettings.InvalidRegion.coord.splice(i, 0, [
                        //Math.floor((coord[i][0] + coord[i-1][0]) / 2),
                        Math.floor(x / draw.sensorScaleX),
                        //Math.floor((coord[i][1] + coord[i-1][1]) / 2)
                        Math.floor(y / draw.sensorScaleY)
                    ]);
                    //params_changed();
                } else {
                    // move location of the selected joint
                    Polygon.moving = true;
                    var px = coord[dhn.index][0];
                    var py = coord[dhn.index][1];
                    coord[dhn.index][0] = Math.floor((dhn.coord.x0 + x - dp.x) / draw.sensorScaleX);
                    coord[dhn.index][1] = Math.floor((dhn.coord.y0 + y - dp.y) / draw.sensorScaleY);

                    // check that anchors located out from new polygon
                    var fa = CvSoftwareSettings.FloorAnchors;
                    var ax1 = fa.x1 * draw.sensorScaleX;
                    var ay1 = fa.y1 * draw.sensorScaleY;
                    var ax2 = fa.x2 * draw.sensorScaleX;
                    var ay2 = fa.y2 * draw.sensorScaleY;
                    var ax3 = fa.x3 * draw.sensorScaleX;
                    var ay3 = fa.y3 * draw.sensorScaleY;
                    var anchorWithoutRegion = true;
                    for (var fi = 0; fi < Math.PI*2; fi+=Math.PI/12) {
                        var xx = (10)*Math.cos(fi)+0.5;
                        var yy = (10)*Math.sin(fi)+0.5;
                        if (Polygon.withPoint(ax1+xx, ay1+yy) || Polygon.withPoint(ax2+xx, ay2+yy) || Polygon.withPoint(ax3+xx, ay3+yy)) {
                            coord[dhn.index][0] = px;
                            coord[dhn.index][1] = py;
                            if (!Anchors.enabled) {
                                Anchors.enabled = true;
                                draw.redraw();
                                setTimeout(function() {Anchors.enabled = false; draw.redraw();}, 200);
                            }
                            anchorWithoutRegion = false;
                        }
                    }
                    if (anchorWithoutRegion && anchorWithinRegion) {
                        anchorWithinRegion = false;
                        messagePanel.hide();
                    }

                    if (dhn.do_delete) {
                        draw.pool.deleteObject('delete' + Math.abs(dhn.index));
                        dhn.do_delete = false;
                    } else
                        return;
                }
            } else
                return;
        }

        var radius = 5;
        draw.canvas.style.cursor = 'default';
        if (!dhn.do_delete)
            dhn.index = null;
        var key = null;
        for (var i = 0; i < coord.length; i++) {
            var cx = coord[i][0] * draw.sensorScaleX;
            var cy = coord[i][1] * draw.sensorScaleY;
            key = 'joint' + i;
            draw.pool.deleteObject(key);
            if (x >= cx - radius && x <= cx + radius && y >= cy - radius && y <= cy + radius) {
                draw.canvas.style.cursor =  'pointer';
                if (dhn.index != null) {
                    if (dhn.index != i) {
                        draw.pool.deleteObject('delete' + Math.abs(dhn.index));
                        dhn.do_delete = false;
                    }
                } else
                if (dhn.do_delete) {
                    Polygon.doCancelClearAll();
                }
                dhn.index = i;
                dhn.coord.x0 = cx;
                dhn.coord.y0 = cy;
                break;
            }
            if (i) { // try check that it is segment
                var x1 = Math.floor(coord[i][0] * draw.sensorScaleX);
                var y1 = Math.floor(coord[i][1] * draw.sensorScaleY);
                var x2 = Math.floor(coord[i-1][0] * draw.sensorScaleX);
                var y2 = Math.floor(coord[i-1][1] * draw.sensorScaleY);
                //cx = (cx + x2) / 2;
                //cy = (cy + y2) / 2;
                key = 'segment' + i;
                draw.pool.deleteObject(key);
                if (Math.abs((x2 - x1) * (y - y1) - (y2 - y1) * (x - x1)) < 1000 &&
                        (((x1 < x2) ? (x > x1 - radius && x < x2 + radius) : (x < x1 + radius && x > x2 - radius)) &&
                        ((y1 < y2) ? (y > y1 - radius && y < y2 + radius) : (y < y1 + radius && y > y2 - radius))))
                    {
                    draw.canvas.style.cursor =  'pointer';
                    if (dhn.index != null) {
                        if (dhn.index != -i) {
                            draw.pool.deleteObject('delete' + Math.abs(dhn.index));
                            dhn.do_delete = false;
                        }
                    } else
                    if (dhn.do_delete) {
                        Polygon.doCancelClearAll();
                    }
                    dhn.index = -i;
                    dhn.coord.x0 = x2;
                    dhn.coord.y0 = y2;
                    dhn.coord.x1 = x1;
                    dhn.coord.y1 = y1;
                    break;
                }
            }
        }
        if (dhn.index != null && !dhn.do_delete) {
            draw.pool.addObject(
                function() {
                    var coord = CvSoftwareSettings.InvalidRegion.coord;
                    var da = draw.area;
                    da.save();
                    da.beginPath();
                    da.fillStyle = da.strokeStyle = Polygon.color;
                    if (dhn.index < 0) {
                        var i = -dhn.index;
                        da.moveTo(coord[i][0] * draw.sensorScaleX + 0.5, coord[i][1] * draw.sensorScaleY + 0.5);
                        i--;
                        da.lineTo(coord[i][0] * draw.sensorScaleX + 0.5, coord[i][1] * draw.sensorScaleY + 0.5);
                        da.lineWidth = Polygon.lineWidth * 2;
                        da.stroke();
                    } else
                    if (dhn.index > 0)
                        da.arc(coord[dhn.index][0] * draw.sensorScaleX + 0.5, coord[dhn.index][1] * draw.sensorScaleY + 0.5, 7, 0, 2 * Math.PI, false);
                    da.globalAlpha = Polygon.lineWidth;
                    da.fill();
                    da.closePath();
                    da.restore();
                    //da.globalAlpha = 1;
                    //da.lineWidth = 0.2;
                    //da.stroke();
                },
                key
            );
            if (Polygon.hInterval)
                clearInterval(Polygon.hInterval);
            Polygon.hInterval = setInterval(function() {
                if (Polygon.lineWidth > 0.9 || Polygon.lineWidth < 0.1)
                    Polygon.dir *= -1;
                Polygon.lineWidth += 0.1 * Polygon.dir;
                draw.redraw();
            }, 20);

        }
    },

    mouseup: function(event) {
        if (!Polygon.enabled)
            return;

        var dp = draw.pointer;
        var dhn = Polygon.highlighted_node;

        Polygon.moving = false;
        if (dhn.index != null) {
            if (dhn.do_delete) { // delete the selected joint
                draw.pool.deleteObject('delete' + Math.abs(dhn.index));
                if (dhn.index < 0) {
                    var i = -dhn.index;
                    CvSoftwareSettings.InvalidRegion.coord.splice(i--, 1);
                    CvSoftwareSettings.InvalidRegion.coord.splice(i, 1);
                } else
                    CvSoftwareSettings.InvalidRegion.coord.splice(dhn.index, 1);
                dhn.index = null;
                dhn.do_delete = false;

                // check that anchors located out from new polygon
                var fa = CvSoftwareSettings.FloorAnchors;
                var ax1 = fa.x1 * draw.sensorScaleX;
                var ay1 = fa.y1 * draw.sensorScaleY;
                var ax2 = fa.x2 * draw.sensorScaleX;
                var ay2 = fa.y2 * draw.sensorScaleY;
                var ax3 = fa.x3 * draw.sensorScaleX;
                var ay3 = fa.y3 * draw.sensorScaleY;
                var anchorWithoutRegion = true;
                for (var fi = 0; fi < Math.PI*2; fi+=Math.PI/12) {
                    var xx = (10)*Math.cos(fi)+0.5;
                    var yy = (10)*Math.sin(fi)+0.5;
                    if (Polygon.withPoint(ax1+xx, ay1+yy) || Polygon.withPoint(ax2+xx, ay2+yy) || Polygon.withPoint(ax3+xx, ay3+yy)) {
                        anchorWithoutRegion = false;
                    }
                }
                if (anchorWithoutRegion && anchorWithinRegion) {
                    anchorWithinRegion = false;
                    messagePanel.hide();
                }


                invalidRegion.style.color = colorIfChanged;
            } else { // select the joint for delete
                if ((dp.x != event.offsetX - draw.canvas.offsetLeft) && (dp.y != event.offsetY - draw.canvas.offsetTop) ||
                    CvSoftwareSettings.InvalidRegion.coord.length < 4)
                    return;

                dhn.do_delete = true;
                draw.pool.addObject(
                    function() {
                        var coord = CvSoftwareSettings.InvalidRegion.coord;
                        var da = draw.area;
                        var x;
                        var y;
                        da.save();
                        if (dhn.index < 0) {
                            var i = -dhn.index;
                            x = (coord[i][0] * draw.sensorScaleX + coord[i-1][0] * draw.sensorScaleX) / 2;
                            y = (coord[i][1] * draw.sensorScaleY + coord[i-1][1] * draw.sensorScaleY) / 2;
                            da.beginPath();
                            da.moveTo(coord[i][0] * draw.sensorScaleX + 0.5, coord[i][1] * draw.sensorScaleY + 0.5);
                            i--;
                            da.lineTo(coord[i][0] * draw.sensorScaleX + 0.5, coord[i][1] * draw.sensorScaleY + 0.5);
                            da.lineWidth = draw.Polygon * 2;
                            da.strokeStyle = Polygon.color;
                            da.stroke();
                            da.closePath();
                        } else {
                            x = coord[dhn.index][0] * draw.sensorScaleX + 0.5;
                            y = coord[dhn.index][1] * draw.sensorScaleY + 0.5;
                        }
                        da.beginPath();
                        da.arc(x, y, 7, 0, 2 * Math.PI, false);
                        da.globalAlpha = 1;
                        da.fillStyle = 'black';
                        da.lineWidth = 1;
                        da.strokeStyle = Polygon.color;
                        da.fill();
                        da.stroke();
                        da.closePath();
                        da.beginPath();
                        da.strokeStyle = 'white';
                        da.moveTo(x - 3.5, y - 3.5);
                        da.lineTo(x + 3.5, y + 3.5);
                        da.moveTo(x + 3.5, y - 3.5);
                        da.lineTo(x - 3.5, y + 3.5);
                        da.lineWidth = Polygon.lineWidth;
                        da.stroke();
                        da.closePath();
                        da.restore();
                    },
                    'delete' + Math.abs(dhn.index)
                );
            }
        } else {
            if (dhn.do_delete || ((dp.x != event.offsetX - draw.canvas.offsetLeft) && (dp.y != event.offsetY - draw.canvas.offsetTop)))
                return;

            // select all polygon
            dhn.do_delete = true;
            dhn.coord.x0 = dp.x;
            dhn.coord.y0 = dp.y;
            if (!Polygon.withPoint(dp.x, dp.y))
                return;

            var canvasPosition = findPos(draw.canvas);
            clearAllButtons.style.left = (canvasPosition.left + dp.x - 50) + 'px';
            clearAllButtons.style.top = (canvasPosition.top + dp.y + 22) + 'px';
            clearAllButtons.style.display = 'block';
            draw.pool.deleteObject('polygon');
            draw.pool.addObject(
                function() {
                    var da = draw.area;
                    var x = dhn.coord.x0;
                    var y = dhn.coord.y0;
                    da.save();
                    da.beginPath();
                    da.arc(x, y, 7, 0, 2 * Math.PI, false);
                    da.globalAlpha = 1;
                    da.fillStyle = 'black';
                    da.lineWidth = 1;
                    da.strokeStyle = Polygon.color;
                    da.fill();
                    da.stroke();
                    da.closePath();
                    da.beginPath();
                    da.strokeStyle = 'white';
                    da.moveTo(x - 3.5, y - 3.5);
                    da.lineTo(x + 3.5, y + 3.5);
                    da.moveTo(x + 3.5, y - 3.5);
                    da.lineTo(x - 3.5, y + 3.5);
                    da.lineWidth = Polygon.lineWidth;
                    da.stroke();
                    da.closePath();
                    da.restore();
                },
                'polygon'
            );
            if (!Polygon.hInterval)
                Polygon.hInterval = setInterval(function() {
                    if (Polygon.lineWidth > 0.9 || Polygon.lineWidth < 0.1)
                        Polygon.dir *= -1;
                    Polygon.lineWidth += 0.1 * Polygon.dir;
                    draw.redraw();
                }, 20);
        }
    },
    draw: function() {
        if (!Polygon.enabled)
            return;
        var dhn = Polygon.highlighted_node;
        var da = draw.area;
        var ip = CvSoftwareSettings.InvalidRegion;
        var coord = ip.coord;
        if (!ip || !coord.length)
            return;

        da.save();

        if (Polygon.invalid) {
            da.beginPath();
            da.rect(0,0, draw.canvas.offsetWidth, draw.canvas.offsetHeight);
            //da.globalAlpha = (dhn.index == null && dhn.do_delete) ? Polygon.lineWidth/2 : 0.3;
            da.globalAlpha = 0.8;
            da.fillStyle = Polygon.color;
            da.fill();
            da.globalCompositeOperation = 'destination-out';
            da.closePath();
        }

        da.beginPath();
        da.moveTo(coord[0][0]*draw.sensorScaleX, coord[0][1]*draw.sensorScaleY);
        for (var i = 1; i < coord.length; i++)
            da.lineTo(coord[i][0]*draw.sensorScaleX, coord[i][1]*draw.sensorScaleY);

        //da.closePath();
        //da.globalAlpha = Polygon.invalid ? 1 : 0.6;
        da.globalAlpha = 0.8;
        da.fillStyle = Polygon.color;
        da.fill();

        da.globalAlpha = 1;
        da.lineWidth = 1;
        da.strokeStyle = Polygon.color;
        da.stroke();
        da.globalCompositeOperation = 'source-over';
        da.closePath();

        da.beginPath();
        da.globalAlpha = 1;
        da.fillStyle = Anchors.color;
        for (var i = 0; i < coord.length; i++) {
            var x = coord[i][0]*draw.sensorScaleX;
            var y = coord[i][1]*draw.sensorScaleY;
            da.moveTo(x, y);
            da.arc(x, y, Anchors.radius*draw.sensorScaleY, 0, 2 * Math.PI, false);
        }

        da.fill();
        da.closePath();
        da.restore();
    },

    withPoint: function(x, y) {
        var pgn = CvSoftwareSettings.InvalidRegion.coord;
        var int = 0;
        var ext = 0;

        var _px = pgn[0][0] * draw.sensorScaleX;
        var _py = pgn[0][1] * draw.sensorScaleY;
        for (var i = pgn.length-1; i >= 0; i--) {
            var px = pgn[i][0] * draw.sensorScaleX;
            var py = pgn[i][1] * draw.sensorScaleY;
            if (((_py <= y) && (py > y)) || ((_py > y) && (py <= y))) {
                var cross = _px + ((px - _px) / (py - _py)) * (y - _py);
                if (cross < x) int++;
                if (cross > x) ext++;
            }
            _px = px;
            _py = py;
        }
        return ((int % 2 == 1) && (ext % 2 == 1));
    }
}
