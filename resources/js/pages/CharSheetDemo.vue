<template>
    <div class="character-generator">
        <h1>Discworld GURPS Character Generator</h1>
        <form @submit.prevent="generateCharacterSheet">
            <div class="form-group">
                <label for="name">Character Name:</label>
                <input v-model="name" type="text" id="name" required />
            </div>
            <div class="form-group">
                <label for="species">Species (Race):</label>
                <select v-model="species" @change="applyDefaults" id="species" required>
                    <option v-for="race in raceData" :key="race.name" :value="race.name">
                        {{ race.name }}
                    </option>
                </select>
            </div>
            <div class="form-group">
                <label for="occupation">Occupation:</label>
                <select v-model="occupation" @change="applyDefaults" id="occupation" required>
                    <option v-for="occupation in occupationData" :key="occupation.name" :value="occupation.name">
                        {{ occupation.name }}
                    </option>
                </select>
            </div>
            <button type="submit">Generate Character Sheet</button>
        </form>

        <div v-if="characterSheet" class="character-sheet">
            <h2>{{ characterSheet.name }}'s Character Sheet</h2>
            <p><strong>Species:</strong> {{ characterSheet.species }}</p>
            <p><strong>Occupation:</strong> {{ characterSheet.occupation }}</p>
            <p><strong>Character Points (CP) Available:</strong> {{ cp }}</p>
            <p><strong>Attributes:</strong></p>
            <ul>
                <li>
                    <label><strong>Strength (ST):</strong></label>
                    <input type="number" v-model="characterSheet.attributes.ST" @change="updateCP" />
                </li>
                <li>
                    <label><strong>Dexterity (DX):</strong></label>
                    <input type="number" v-model="characterSheet.attributes.DX" @change="updateCP" />
                </li>
                <li>
                    <label><strong>Intelligence (IQ):</strong></label>
                    <input type="number" v-model="characterSheet.attributes.IQ" @change="updateCP" />
                </li>
                <li>
                    <label><strong>Health (HT):</strong></label>
                    <input type="number" v-model="characterSheet.attributes.HT" @change="updateCP" />
                </li>
            </ul>

            <div class="skills-section">
                <h3>Fertigkeiten (Skills):</h3>
                <ul>
                    <li v-for="(skill, index) in characterSheet.skills" :key="index">
                        <select v-model="skill.name" @change="validateSkill(skill, index)">
                            <option value="" disabled>Select Skill</option>
                            <option v-for="skillOption in skillData" :key="skillOption.name" :value="skillOption.name">
                                {{ skillOption.name }}
                            </option>
                        </select>
                        <input type="number" v-model="skill.level" @change="updateCP" placeholder="Level" />
                        <span v-if="skill.error" class="error">{{ skill.error }}</span>
                        <button @click="removeSkill(index)">Remove</button>
                    </li>
                </ul>
                <button @click="addSkill">Add Skill</button>
            </div>

            <div class="pros-cons-section">
                <h3>Pros (Advantages) and Cons (Disadvantages):</h3>
                <ul>
                    <li v-for="(pro, index) in characterSheet.pros" :key="index">
                        <select v-model="pro.name" @change="validatePro(pro, index)">
                            <option value="" disabled>Select Advantage</option>
                            <option v-for="proOption in prosData" :key="proOption.name" :value="proOption.name">
                                {{ proOption.name }}
                            </option>
                        </select>
                        <input type="number" v-model="pro.cost" @change="updateCP" placeholder="Cost" disabled />
                        <span v-if="pro.error" class="error">{{ pro.error }}</span>
                        <button @click="removePro(index)">Remove</button>
                    </li>
                </ul>
                <button @click="addPro">Add Advantage</button>

                <ul>
                    <li v-for="(con, index) in characterSheet.cons" :key="index">
                        <select v-model="con.name" @change="validateCon(con, index)">
                            <option value="" disabled>Select Disadvantage</option>
                            <option v-for="conOption in consData" :key="conOption.name" :value="conOption.name">
                                {{ conOption.name }}
                            </option>
                        </select>
                        <input type="number" v-model="con.cost" @change="updateCP" placeholder="Cost" disabled />
                        <span v-if="con.error" class="error">{{ con.error }}</span>
                        <button @click="removeCon(index)">Remove</button>
                    </li>
                </ul>
                <button @click="addCon">Add Disadvantage</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            name: '',
            species: 'Human',
            occupation: 'Wizard',
            cp: 100,
            characterSheet: null,
            raceData: [
                { name: 'Human', defaultAttributes: { ST: 10, DX: 10, IQ: 10, HT: 10 } },
                { name: 'Dwarf', defaultAttributes: { ST: 12, DX: 9, IQ: 10, HT: 11 } },
                { name: 'Troll', defaultAttributes: { ST: 15, DX: 8, IQ: 8, HT: 12 } },
                { name: 'Gnome', defaultAttributes: { ST: 8, DX: 12, IQ: 10, HT: 9 } },
                { name: 'Vampire', defaultAttributes: { ST: 15, DX: 11, IQ: 10, HT: 10 } },
            ],
            occupationData: [
                { name: 'Wizard', attributeModifiers: { IQ: 2 } },
                { name: 'Witch', attributeModifiers: { IQ: 1, HT: 1 } },
                { name: 'Watchman', attributeModifiers: { ST: 1, DX: 1 } },
                { name: 'Thief', attributeModifiers: { DX: 2 } },
            ],
            skillData: [
                { name: 'L-Space Theory', cost: 4, prerequisites: [{ skill: 'Research', level: 15 }] },
                { name: 'Alchemy', cost: 2, prerequisites: [{ attribute: 'IQ', value: 6 }] },
                { name: 'Swordsmanship', cost: 2 },
                { name: 'Thievery', cost: 1 },
            ],
            prosData: [
                { name: 'Good Reputation', cost: 5 },
                { name: 'Magical Talent', cost: 10 },
            ],
            consData: [
                { name: 'Magical Incompetence', cost: -5 },
                { name: 'Cowardice', cost: -10 },
            ],
        };
    },
    methods: {
        generateCharacterSheet() {
            const baseAttributes = this.calculateBaseAttributes();

            this.characterSheet = {
                name: this.name,
                species: this.species,
                occupation: this.occupation,
                attributes: {
                    ST: baseAttributes.ST,
                    DX: baseAttributes.DX,
                    IQ: baseAttributes.IQ,
                    HT: baseAttributes.HT,
                },
                skills: [],
                pros: [],
                cons: [],
            };
        },
        applyDefaults() {
            const race = this.raceData.find((r) => r.name === this.species);
            const occupation = this.occupationData.find((o) => o.name === this.occupation);

            if (race) {
                this.characterSheet.attributes = { ...race.defaultAttributes };
            }

            if (occupation) {
                Object.keys(occupation.attributeModifiers).forEach((key) => {
                    this.characterSheet.attributes[key] += occupation.attributeModifiers[key];
                });
            }

            this.updateCP();
        },
        calculateBaseAttributes() {
            const race = this.raceData.find((r) => r.name === this.species);
            const occupation = this.occupationData.find((o) => o.name === this.occupation);

            let attributes = { ST: 10, DX: 10, IQ: 10, HT: 10 }; // Default baseline attributes

            if (race) {
                attributes = { ...attributes, ...race.defaultAttributes };
            }

            if (occupation) {
                Object.keys(occupation.attributeModifiers).forEach((key) => {
                    attributes[key] += occupation.attributeModifiers[key];
                });
            }

            return attributes;
        },
        updateCP() {
            let spentCP = 0;

            spentCP += (this.characterSheet.attributes.ST - 10) * 10;
            spentCP += (this.characterSheet.attributes.DX - 10) * 20;
            spentCP += (this.characterSheet.attributes.IQ - 10) * 20;
            spentCP += (this.characterSheet.attributes.HT - 10) * 10;

            this.characterSheet.skills.forEach((skill) => {
                spentCP += skill.level;
            });

            this.characterSheet.pros.forEach((pro) => {
                spentCP += pro.cost;
            });

            this.characterSheet.cons.forEach((con) => {
                spentCP -= con.cost;
            });

            this.cp = 100 - spentCP;
        },
        addSkill() {
            this.characterSheet.skills.push({ name: '', level: 1, error: '' });
        },
        removeSkill(index) {
            this.characterSheet.skills.splice(index, 1);
            this.updateCP();
        },
        validateSkill(skill, index) {
            const skillData = this.skillData.find((s) => s.name === skill.name);
            if (skillData && skillData.prerequisites) {
                skill.error = this.checkPrerequisites(skillData.prerequisites) ? '' : 'Prerequisites not met';
                skill.cost = skillData.cost;
            } else {
                skill.error = '';
            }
            this.characterSheet.skills.splice(index, 1, skill);
            this.updateCP();
        },
        addPro() {
            this.characterSheet.pros.push({ name: '', cost: 0, error: '' });
        },
        removePro(index) {
            this.characterSheet.pros.splice(index, 1);
            this.updateCP();
        },
        validatePro(pro, index) {
            const proData = this.prosData.find((p) => p.name === pro.name);
            if (proData) {
                pro.cost = proData.cost;
                pro.error = '';
            } else {
                pro.error = 'Invalid Advantage';
            }
            this.characterSheet.pros.splice(index, 1, pro);
            this.updateCP();
        },
        addCon() {
            this.characterSheet.cons.push({ name: '', cost: 0, error: '' });
        },
        removeCon(index) {
            this.characterSheet.cons.splice(index, 1);
            this.updateCP();
        },
        validateCon(con, index) {
            const conData = this.consData.find((c) => c.name === con.name);
            if (conData) {
                con.cost = conData.cost;
                con.error = '';
            } else {
                con.error = 'Invalid Disadvantage';
            }
            this.characterSheet.cons.splice(index, 1, con);
            this.updateCP();
        },
        checkPrerequisites(prerequisites) {
            for (const prerequisite of prerequisites) {
                if (prerequisite.skill) {
                    const requiredSkill = this.characterSheet.skills.find((skill) => skill.name === prerequisite.skill);
                    if (!requiredSkill || requiredSkill.level < prerequisite.level) {
                        return false;
                    }
                } else if (prerequisite.attribute) {
                    const attrValue = this.characterSheet.attributes[prerequisite.attribute];
                    if (attrValue < prerequisite.value) {
                        return false;
                    }
                }
            }
            return true;
        },
    },
};
</script>

<style scoped>
.character-generator {
    font-family: Arial, sans-serif;
    max-width: 600px;
    margin: 20px auto;
    padding: 20px;
    border: 1px solid #ccc;
    border-radius: 5px;
    background-color: #f9f9f9;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 8px;
    box-sizing: border-box;
}

button {
    padding: 10px 15px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-right: 10px;
}

button:hover {
    background-color: #0056b3;
}

.character-sheet {
    margin-top: 20px;
    padding: 15px;
    border: 1px solid #007bff;
    border-radius: 5px;
    background-color: #e9f7fd;
}

.skills-section,
.pros-cons-section {
    margin-top: 20px;
}

.skills-section ul,
.pros-cons-section ul {
    list-style: none;
    padding: 0;
}

.skills-section li,
.pros-cons-section li {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.skills-section li select,
.pros-cons-section li select,
.skills-section li input,
.pros-cons-section li input {
    margin-right: 10px;
    padding: 5px;
    width: 120px;
}

.skills-section li button,
.pros-cons-section li button {
    padding: 5px 10px;
    background-color: #dc3545;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.skills-section li button:hover,
.pros-cons-section li button:hover {
    background-color: #c82333;
}

.error {
    color: red;
    font-size: 0.8em;
    margin-left: 10px;
}
</style>
