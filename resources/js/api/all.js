// all.js

export function getConversation(id) {
    return new Promise((resolve, reject) => {
        axios.get('/api/conversations/' + id).then((response) => {
            resolve(response);
        }).catch(reject);
    });
}

export function getConversations(page) {
    return new Promise((resolve, reject) => {
        axios.get('/api/conversations?page=' + page).then((response) => {
            resolve(response);
        }).catch(reject);
    });
}

export function storeConversationReply(id, { body }) {
    return new Promise((resolve, reject) => {
        axios.post('/api/conversations/' + id + '/reply', {
            body
        }).then((response) => {
            resolve(response);
        }).catch(reject);
    });
}

export function storeConversation({ body, recipientIds }) {
    return new Promise((resolve, reject) => {
        axios.post('/api/conversations', {
            body,
            recipients: recipientIds
        }).then((response) => {
            resolve(response);
        }).catch(reject);
    });
}



export function storeUserSearch(query) {
    return new Promise((resolve, reject) => {
        axios.post('/api/users/search', {
            query: query
        }).then((response) => {
            resolve(response);
        }).catch(reject);
    });
}
