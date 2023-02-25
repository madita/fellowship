export default {
    groupTerms(array) {
        let resultObj = {};


        for (let i = 0; i < array.length; i++) {
            let currentWord = array[i].term;
            let firstChar = currentWord.slug[0].toLowerCase();
            let innerArr = [];
            if (resultObj[firstChar] === undefined) {
                innerArr.push(currentWord);
                resultObj[firstChar] = innerArr
            } else {
                resultObj[firstChar].push(currentWord)
            }
        }
        return resultObj
    }
}
