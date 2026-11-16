const backgroundColors = [
    '#FFEB3B' , '#FFC107','#FF9800', '#FF5722',
    '#F44336', '#FF4081', '#65113e', '#941024',
    '#3F51B5', '#2196F3', '#03A9F4', '#00BCD4',
    '#009688', '#4CAF50', '#8BC34A', '#88dc39',
    '#8a69b9', '#792092', '#5a176c', '#340741']


export default {

    randomBackgroundColor (seed, colours) {
        let coloursArr = colours || backgroundColors
        return coloursArr[seed % (coloursArr.length)]
    },

    lightenColor (hex, amt) {
        // From https://css-tricks.com/snippets/javascript/lighten-darken-color/
        var usePound = false

        let amtTemp= amt || 80

        if (hex[0] === '#') {
            hex = hex.slice(1)
            usePound = true
        }

        var num = parseInt(hex, 16)
        var r = (num >> 16) + amtTemp

        if (r > 255) r = 255
        else if (r < 0) r = 0

        var b = ((num >> 8) & 0x00FF) + amtTemp

        if (b > 255) b = 255
        else if (b < 0) b = 0

        var g = (num & 0x0000FF) + amtTemp

        if (g > 255) g = 255
        else if (g < 0) g = 0

        return (usePound ? '#' : '') + (g | (b << 8) | (r << 16)).toString(16)
    }

};

