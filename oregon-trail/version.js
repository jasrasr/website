/*
File Name     : version.js
Author        : Jason Lamb (with help from ChatGPT)
Description   : Shared project revision and last-updated date for the main game page.
Revision      : 1.0.0
Created Date  : 2026-10-19
Modified Date : 2026-10-19
Changelog     :
  Rev 1.0.0 - Established independent file revision tracking; Shared project revision and last-updated date for the main game page.
*/
(function(root){
  'use strict';
  const version=Object.freeze({revision:'1.0.0',updated:'2026-10-19'});
  if(typeof module!=='undefined'&&module.exports)module.exports=version;else root.TrailVersion=version;
})(typeof globalThis!=='undefined'?globalThis:this);
