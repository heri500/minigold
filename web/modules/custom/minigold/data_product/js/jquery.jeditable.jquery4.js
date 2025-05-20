/*! jquery-jeditable for jQuery 4 - Modified from https://github.com/NicolasCARPi/jquery_jeditable */

(function($) {
  "use strict";

  $.fn.editableAriaShim = function() {
    return this.attr({
      role: "button",
      tabindex: 0
    });
  };

  $.fn.editable = function(target, options) {
    // 'disable', 'enable', and 'destroy' commands
    if (target === "disable") {
      $(this).data("disabled.editable", true);
      return this;
    }
    if (target === "enable") {
      $(this).data("disabled.editable", false);
      return this;
    }
    if (target === "destroy") {
      $(this).off($(this).data("event.editable"))
        .removeData("disabled.editable")
        .removeData("event.editable");
      return this;
    }

    var settings = $.extend({}, $.fn.editable.defaults, {
      target: target
    }, options);

    var plugin = $.editable.types[settings.type].plugin || function() {};
    var submit = $.editable.types[settings.type].submit || function() {};
    var buttons = $.editable.types[settings.type].buttons || $.editable.types.defaults.buttons;
    var content = $.editable.types[settings.type].content || $.editable.types.defaults.content;
    var element = $.editable.types[settings.type].element || $.editable.types.defaults.element;
    var reset = $.editable.types[settings.type].reset || $.editable.types.defaults.reset;
    var destroy = $.editable.types[settings.type].destroy || $.editable.types.defaults.destroy;

    var callback = settings.callback || function() {};
    var intercept = settings.intercept || function(s) {
      return s;
    };
    var onedit = settings.onedit || function() {};
    var onsubmit = settings.onsubmit || function() {};
    var onreset = settings.onreset || function() {};
    var onerror = settings.onerror || reset;

    if (settings.tooltip) {
      $(this).attr("title", settings.tooltip);
    }

    return this.each(function() {
      var self = this;

      $(this).data("event.editable", settings.event);

      if (!$(this).html().trim()) {
        $(this).html(settings.placeholder);
      }

      // If target is destroy, just call the destroy method
      if (target === "destroy") {
        destroy.apply($(this).find("form"), [settings, self]);
        return;
      }

      $(this).on(settings.event, function(e) {
        if ($(this).data("disabled.editable") === true || e.which === 9 || self.editing ||
          onedit.apply(this, [settings, self, e]) === false) {
          return;
        }

        // Handle 'before' callback
        if (settings.before && typeof settings.before === "function") {
          settings.before(e);
        } else if (settings.before && typeof settings.before !== "function") {
          throw "The 'before' option needs to be provided as a function!";
        }

        e.preventDefault();
        e.stopPropagation();

        if (settings.tooltip) {
          $(self).removeAttr("title");
        }

        // Check if content is the placeholder
        if ($(this).html().toLowerCase().replace(/(;|"|\/)/g, "") ===
          settings.placeholder.toLowerCase().replace(/(;|"|\/)/g, "")) {
          $(this).html("");
        }

        self.editing = true;
        self.revert = $(self).text();
        $(self).html("");

        var form = $("<form />");

        // Handle CSS class for form
        if (settings.cssclass) {
          if (settings.cssclass === "inherit") {
            form.attr("class", $(self).attr("class"));
          } else {
            form.attr("class", settings.cssclass);
          }
        }

        // Handle style for form
        if (settings.style) {
          if (settings.style === "inherit") {
            form.attr("style", $(self).attr("style"));
            form.css("display", $(self).css("display"));
          } else {
            form.attr("style", settings.style);
          }
        }

        // Add label if required
        if (settings.label) {
          form.append("<label>" + settings.label + "</label>");
        }

        // Form ID if specified
        if (settings.formid) {
          form.attr("id", settings.formid);
        }

        var input_content;
        var t;
        var input = element.apply(form, [settings, self]);

        // Handle input CSS class
        if (settings.inputcssclass) {
          if (settings.inputcssclass === "inherit") {
            input.attr("class", $(self).attr("class"));
          } else {
            input.attr("class", settings.inputcssclass);
          }
        }

        var isSubmitting = false;

        // Handle loading URL
        if (settings.loadurl) {
          t = self.setTimeout(function() {
            input.prop("disabled", true);
          }, 100);

          $(self).html(settings.loadtext);

          var loaddata = {};
          loaddata[settings.id] = self.id;

          if (typeof settings.loaddata === "function") {
            $.extend(loaddata, settings.loaddata.apply(self, [self.revert, settings]));
          } else {
            $.extend(loaddata, settings.loaddata);
          }

          $.ajax({
            type: settings.loadtype,
            url: settings.loadurl,
            data: loaddata,
            async: false,
            cache: false,
            success: function(result) {
              self.clearTimeout(t);
              input_content = result;
              input.prop("disabled", false);
            }
          });
        } else if (settings.data) {
          input_content = settings.data;
          if (typeof settings.data === "function") {
            input_content = settings.data.apply(self, [self.revert, settings]);
          }
        } else {
          input_content = self.revert;
        }

        content.apply(form, [input_content, settings, self]);
        input.attr("name", settings.name);

        // Adjust width if needed
        if (settings.width !== "none") {
          var adj_width = settings.width - (input.outerWidth(true) - settings.width);
          input.width(adj_width);
        }

        buttons.apply(form, [settings, self]);

        if (settings.showfn && typeof settings.showfn === "function") {
          form.hide();
        }

        $(self).html("");
        $(self).append(form);

        if (settings.showfn && typeof settings.showfn === "function") {
          settings.showfn(form);
        }

        plugin.apply(form, [settings, self]);

        form.find(":input:visible:enabled:first").trigger("focus");

        if (settings.select) {
          input.trigger("select");
        }

        // Handle key events
        $(this).on("keydown", function(e) {
          if (e.which === 27) {
            // Escape
            e.preventDefault();
            reset.apply(form, [settings, self]);
          } else if (e.which === 13 && e.shiftKey) {
            // Shift + Enter
            e.preventDefault();
            form.trigger("submit");
          }
        });

        // Handle onblur option
        if (settings.onblur === "cancel") {
          input.on("blur", function(e) {
            t = self.setTimeout(function() {
              reset.apply(form, [settings, self]);
            }, 500);
          });
        } else if (settings.onblur === "submit") {
          input.on("blur", function(e) {
            t = self.setTimeout(function() {
              form.trigger("submit");
            }, 200);
          });
        } else if (typeof settings.onblur === "function") {
          input.on("blur", function(e) {
            if (settings.onblur.apply(self, [input.val(), settings, form]) === false) {
              reset.apply(form, [settings, self]);
            }
          });
        }

        // Handle form submission
        form.on("submit", function(e) {
          e.preventDefault();
          e.stopPropagation();

          if (isSubmitting) {
            return false;
          }

          isSubmitting = true;

          if (t) {
            self.clearTimeout(t);
          }

          // Apply onsubmit callback
          isSubmitting = onsubmit.apply(form, [settings, self]) !== false;

          // Apply default submit
          if (isSubmitting) {
            isSubmitting = submit.apply(form, [settings, self]) !== false;
          }

          if (isSubmitting) {
            // Handle custom target function
            if (typeof settings.target === "function") {
              var responseHandler = function(value, complete) {
                isSubmitting = false;
                if (complete !== false) {
                  $(self).html(value);
                  self.editing = false;
                  callback.apply(self, [self.innerText, settings]);

                  // Re-add placeholder if needed
                  if (!$(self).html().trim()) {
                    $(self).html(settings.placeholder);
                  }
                }
              };

              var userTarget = settings.target.apply(self, [input.val(), settings, responseHandler]);

              if (userTarget !== false && userTarget !== undefined) {
                responseHandler(userTarget, userTarget);
              }
            } else {
              // Handle standard Ajax submission
              var submitdata = {};
              submitdata[settings.name] = input.val();
              submitdata[settings.id] = self.id;

              if (typeof settings.submitdata === "function") {
                $.extend(submitdata, settings.submitdata.apply(self, [self.revert, settings, submitdata]));
              } else {
                $.extend(submitdata, settings.submitdata);
              }

              // Support for PUT method
              if (settings.method === "PUT") {
                submitdata["_method"] = "put";
              }

              $(self).html(settings.indicator);

              var ajaxoptions = {
                type: "POST",
                complete: function(xhr, status) {
                  isSubmitting = false;
                },
                data: submitdata,
                dataType: "html",
                url: settings.target,
                success: function(result, status) {
                  result = intercept.apply(self, [result, status]);

                  if (ajaxoptions.dataType === "html") {
                    $(self).html(result);
                  }

                  self.editing = false;
                  callback.apply(self, [result, settings, submitdata]);

                  // Re-add placeholder if needed
                  if (!$(self).html().trim()) {
                    $(self).html(settings.placeholder);
                  }
                },
                error: function(xhr, status, error) {
                  onerror.apply(form, [settings, self, xhr]);
                }
              };

              $.extend(ajaxoptions, settings.ajaxoptions);
              $.ajax(ajaxoptions);
            }
          }

          $(self).attr("title", settings.tooltip);
          return false;
        });
      });

      // Reset function
      self.reset = function(form) {
        if (self.editing && onreset.apply(form, [settings, self]) !== false) {
          $(self).text(self.revert);
          self.editing = false;

          // Re-add placeholder if needed
          if (!$(self).html().trim()) {
            $(self).html(settings.placeholder);
          }

          if (settings.tooltip) {
            $(self).attr("title", settings.tooltip);
          }
        }
      };

      // Destroy function
      self.destroy = function(form) {
        $(self).off($(self).data("event.editable"))
          .removeData("disabled.editable")
          .removeData("event.editable");

        self.clearTimeouts();

        if (self.editing) {
          reset.apply(form, [settings, self]);
        }
      };

      // Timeout management functions
      self.clearTimeout = function(t) {
        var timeouts = $(self).data("timeouts");
        if (clearTimeout(t), timeouts) {
          var i = timeouts.indexOf(t);
          if (i > -1) {
            timeouts.splice(i, 1);
            if (timeouts.length <= 0) {
              $(self).removeData("timeouts");
            }
          } else {
            console.warn("jeditable clearTimeout could not find timeout " + t);
          }
        }
      };

      self.clearTimeouts = function() {
        var timeouts = $(self).data("timeouts");
        if (timeouts) {
          for (var i = 0, n = timeouts.length; i < n; ++i) {
            clearTimeout(timeouts[i]);
          }
          timeouts.length = 0;
          $(self).removeData("timeouts");
        }
      };

      self.setTimeout = function(callback, time) {
        var timeouts = $(self).data("timeouts");
        var t = setTimeout(function() {
          callback();
          self.clearTimeout(t);
        }, time);

        if (!timeouts) {
          timeouts = [];
          $(self).data("timeouts", timeouts);
        }

        timeouts.push(t);
        return t;
      };
    });
  };

  // Helper function to support html5 input types
  var _supportInType = function(type) {
    var i = document.createElement("input");
    i.setAttribute("type", type);
    return i.type !== "text" ? type : "text";
  };

  $.editable = {
    types: {
      defaults: {
        element: function(settings, original) {
          var input = $('<input type="hidden">');
          $(this).append(input);
          return input;
        },
        content: function(string, settings, original) {
          $(this).find(":input:first").val(string);
        },
        reset: function(settings, original) {
          original.reset(this);
        },
        destroy: function(settings, original) {
          original.destroy(this);
        },
        buttons: function(settings, original) {
          var submit, cancel, form = this;

          if (settings.submit) {
            if (settings.submit.match(/>$/)) {
              submit = $(settings.submit).on("click", function() {
                if (submit.attr("type") !== "submit") {
                  form.trigger("submit");
                }
              });
            } else {
              submit = $('<button type="submit" />');
              submit.html(settings.submit);

              if (settings.submitcssclass) {
                submit.addClass(settings.submitcssclass);
              }
            }

            $(this).append(submit);
          }

          if (settings.cancel) {
            if (settings.cancel.match(/>$/)) {
              cancel = $(settings.cancel);
            } else {
              cancel = $('<button type="cancel" />');
              cancel.html(settings.cancel);

              if (settings.cancelcssclass) {
                cancel.addClass(settings.cancelcssclass);
              }
            }

            $(this).append(cancel);

            $(cancel).on("click", function(event) {
              var reset = (typeof $.editable.types[settings.type].reset === "function") ?
                $.editable.types[settings.type].reset :
                $.editable.types.defaults.reset;

              reset.apply(form, [settings, original]);
              return false;
            });
          }
        }
      },

      text: {
        element: function(settings, original) {
          var input = $("<input />").attr({
            autocomplete: "off",
            list: settings.list,
            maxlength: settings.maxlength,
            pattern: settings.pattern,
            placeholder: settings.placeholder,
            tooltip: settings.tooltip,
            type: "text"
          });

          if (settings.width !== "none") {
            input.css("width", settings.width);
          }

          if (settings.height !== "none") {
            input.css("height", settings.height);
          }

          if (settings.size) {
            input.attr("size", settings.size);
          }

          if (settings.maxlength) {
            input.attr("maxlength", settings.maxlength);
          }

          $(this).append(input);
          return input;
        }
      },

      textarea: {
        element: function(settings, original) {
          var textarea = $("<textarea></textarea>");

          if (settings.rows) {
            textarea.attr("rows", settings.rows);
          } else if (settings.height !== "none") {
            textarea.height(settings.height);
          }

          if (settings.cols) {
            textarea.attr("cols", settings.cols);
          } else if (settings.width !== "none") {
            textarea.width(settings.width);
          }

          if (settings.maxlength) {
            textarea.attr("maxlength", settings.maxlength);
          }

          $(this).append(textarea);
          return textarea;
        }
      },

      select: {
        element: function(settings, original) {
          var select = $("<select />");

          if (settings.multiple) {
            select.attr("multiple", "multiple");
          }

          $(this).append(select);
          return select;
        },

        content: function(data, settings, original) {
          var json;
          if (data.constructor === String) {
            json = JSON.parse(data);
          } else {
            json = data;
          }

          var tuples = [];

          if (Array.isArray(json) && json.every(Array.isArray)) {
            tuples = json;
            json = {};
            tuples.forEach(function(e) {
              json[e[0]] = e[1];
            });
          } else {
            for (var key in json) {
              tuples.push([key, json[key]]);
            }
          }

          if (settings.sortselectoptions) {
            tuples.sort(function(a, b) {
              a = a[1];
              b = b[1];
              return a < b ? -1 : (a > b ? 1 : 0);
            });
          }

          for (var i = 0; i < tuples.length; i++) {
            var key = tuples[i][0];
            var value = tuples[i][1];

            if (json.hasOwnProperty(key)) {
              if (key !== "selected") {
                var option = $("<option />").val(key).append(value);

                if (json.selected === key ||
                  key === String.prototype.trim.call(original.revert == null ? "" : original.revert)) {
                  $(option).prop("selected", "selected");
                }

                $(this).find("select").append(option);
              }
            }
          }

          // Auto submit on change if no submit button defined
          if (!settings.submit) {
            var form = this;
            $(this).find("select").change(function() {
              form.trigger("submit");
            });
          }
        }
      },

      number: {
        element: function(settings, original) {
          var input = $("<input />").attr({
            maxlength: settings.maxlength,
            placeholder: settings.placeholder,
            min: settings.min,
            max: settings.max,
            step: settings.step,
            tooltip: settings.tooltip,
            type: _supportInType("number")
          });

          if (settings.width !== "none") {
            input.css("width", settings.width);
          }

          $(this).append(input);
          return input;
        }
      },

      email: {
        element: function(settings, original) {
          var input = $("<input />").attr({
            maxlength: settings.maxlength,
            placeholder: settings.placeholder,
            tooltip: settings.tooltip,
            type: _supportInType("email")
          });

          if (settings.width !== "none") {
            input.css("width", settings.width);
          }

          $(this).append(input);
          return input;
        }
      },

      url: {
        element: function(settings, original) {
          var input = $("<input />").attr({
            maxlength: settings.maxlength,
            pattern: settings.pattern,
            placeholder: settings.placeholder,
            tooltip: settings.tooltip,
            type: _supportInType("url")
          });

          if (settings.width !== "none") {
            input.css("width", settings.width);
          }

          $(this).append(input);
          return input;
        }
      }
    },

    addInputType: function(name, input) {
      $.editable.types[name] = input;
    }
  };

  $.fn.editable.defaults = {
    name: "value",
    id: "id",
    type: "text",
    width: "auto",
    height: "auto",
    event: "click.editable keydown.editable",
    onblur: "cancel",
    tooltip: "Click to edit",
    loadtype: "GET",
    loadtext: "Loading...",
    placeholder: "Click to edit",
    sortselectoptions: false,
    loaddata: {},
    submitdata: {},
    ajaxoptions: {}
  };

})(jQuery);
