# BAR-SUBA4-Webservice
Public plant (A. thal) bioinformatics API for cellular localization data of genes

API ReadMe template credits: https://gist.github.com/iros/3426278

**SUBA4 API @ Bio-Analytic Resource for Plant Biology (http://bar.utoronto.ca/)**
----
  Returns JSON data from a POST request regarding the expected and (optionally) predicted localizations for a gene protein product in A. thal. Data from our Subcellular Localization Database for Arabidopsis Proteins v4 database at the BAR (SUBA4, http://suba.live/)

* **URL**

  bar.utoronto.ca/~vlau/testing_suba4.php

* **Method:**
  
  `POST`
  
*  **URL Params**

  None

* **Data Params**

  * **Content:** 
  ```json
  {
    "AGI_IDs": ["At1g01020","At1g01030"],
    "include_predicted": true
  }
  ```

* **Success Response:**

  * Note that the responses have a ```includes_predicted``` and ```includes_experimental``` indicate if predicted or experimental data was included in calculating of the localization score for that gene, respectively.
  * Higher scores indicate stronger confidence in a localization
  
  * **Code:** 200 <br />
    **Content:** 
    ```json
    [
    {
        "id": "AT1G01020", 
        "includes_predicted": "yes",
        "data": {
            "mitochondrion": 12,
            "nucleus": 4,
            "cytosol": 2,
            "plasma membrane": 6,
            "plastid": 4,
            "endoplasmic reticulum": 32,
            "extracellular": 2,
            "golgi": 2
        },
        "includes_experimental": "yes"
    },
    {
        "id": "AT1G01030",
        "includes_predicted": "yes",
        "data": {
            "nucleus": 40,
            "mitochondrion": 4,
            "cytosol": 2
        },
        "includes_experimental": "yes"
    }]
    ```
 
* **Error Response:**

  * **Code:** 200 <br />
    **Content:**
    ```json
    {
    "status": "fail",
    "result": "Array of AGI IDs were not properly formatted in the JSON request! " 
    }
    ```
    
* **Sample Call:**
  
  ```javascript
  var settings = {
  "async": true,
  "crossDomain": true,
  "url": "https://bar.utoronto.ca/~vlau/testing_suba4.php",
  "method": "POST",
  "headers": {
    "Content-Type": "application/json",
    "Cache-Control": "no-cache",
    "Postman-Token": "ab2bb38f-dea7-b041-be55-948e3509ae9b"
  },
  "processData": false,
  "data": "{"AGI_IDs": ["AT2G34970", "AT4G02640"], "include_predicted": true}"
  }

  $.ajax(settings).done(function (response) {
    console.log(response);
  });
  ```

* **Notes:**

 * Please be patient as we add more appropriate status codes and change the URL. 
