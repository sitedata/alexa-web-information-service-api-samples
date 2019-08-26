//
// Copyright 2019 Amazon.com, Inc. and its affiliates. All Rights Reserved.
//
// Licensed under the MIT License. See the LICENSE accompanying this file
// for the specific language governing permissions and limitations under
// the License.
//

const rp   = require('request-promise')
const util = require('util');
const readline = require('readline');

const apiHost = 'awis.stage.api.alexa.com'
const fs = require('fs');

global.fetch = require("node-fetch");

function callAwis(apikey, site) {
  var uri = '/api?Action=urlInfo&Output=json&ResponseGroup=Rank&Url=' + site + "&Output=json";

  var opts = {
  	host: apiHost,
  	path: uri,
  	uri: 'https://' + apiHost + uri,
  	json: true,
  	headers: {'x-api-key': apikey}
  }

  rp(opts)
  .then( (html)=> console.log(`${JSON.stringify(html, null, 2)}`) )
  .catch( (e)=> console.log('failed:'+e))
}

if (process.argv.length != 4) {
  console.log(`Usage: node ${process.argv[1]} APIKEY SITE`);
  process.exit(0);
}

callAwis(process.argv[2], process.argv[3])
