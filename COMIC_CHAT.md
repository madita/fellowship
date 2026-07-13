# MS Comic Chat Style View 🎨💬

A nostalgic throwback to Microsoft's Comic Chat from the late 90s! This feature transforms the IRC client into a comic book-style interface with avatars, emotions, speech bubbles, and comic panel layouts.

## ✨ Features

### Comic Panel Layout
- 📖 **Comic Book Panels** - Messages displayed in comic strip format
- 🎭 **Character Avatars** - 8 different characters to choose from
- 💭 **Speech Bubbles** - Different bubble types (speech, thought, whisper, shout)
- 🎨 **Backgrounds** - 6 scenic backgrounds (Room, Office, Outdoor, Space, Cafe, Beach)
- 📏 **Auto-Layout** - 3-4 messages per panel for optimal reading

### Emotions & Gestures
**7 Emotions:**
- 😐 **Normal** - Default neutral expression
- 😊 **Happy** - Cheerful and positive
- 😢 **Sad** - Melancholy or disappointed
- 😠 **Angry** - Upset or frustrated
- 😲 **Surprised** - Shocked or amazed
- 😕 **Confused** - Puzzled or uncertain
- 🤩 **Excited** - Enthusiastic or thrilled

**6 Gestures:**
- 👋 **Wave** - Greeting gesture
- 😂 **Laugh** - Laughing out loud
- 💭 **Think** - Thoughtful (uses thought bubble)
- 📢 **Shout** - Shouting (uses shout bubble with bold text)
- 🤫 **Whisper** - Quiet message (uses dashed bubble)
- None - No special gesture

### Speech Bubble Types
- **Speech** (default) - Standard rounded bubble
- **Thought** - Cloud-shaped bubble for thinking
- **Whisper** - Dashed border, italic text, grey background
- **Shout** - Thick border, bold text, red background

## 🚀 How to Use

### Switching to Comic View

1. Open any IRC channel
2. Click the **comic book icon** (📖) in the channel header
3. The view switches from classic IRC to Comic Chat!

### Selecting Your Character

1. Click the **three dots** (⋮) next to your connection
2. Select "**Choose Character**"
3. Pick from 8 available characters:
   - 🐱 Cat
   - 🐕 Dog
   - 🤖 Robot
   - 👽 Alien
   - 🧙 Wizard
   - 🥷 Ninja
   - 🏴‍☠️ Pirate
   - ⚔️ Knight
4. Choose your background scene
5. Click **Save**

### Sending Expressive Messages

**In Comic Mode:**

1. Type your message as usual
2. Select an **emotion** (happy, sad, angry, etc.)
3. Choose a **gesture** (wave, laugh, think, etc.)
4. Press Enter or click Send

The message appears in a comic panel with:
- Your character showing the chosen emotion
- A speech bubble matching your gesture
- Proper comic book styling

**Example:**
```
Emotion: Happy
Gesture: Wave
Message: "Hello everyone!"

Result: Character appears cheerful, waving, 
        with speech bubble saying "Hello everyone!"
```

## 🎭 Character Animations

Characters change based on your selected emotion:
- **Happy** - Bouncing animation
- **Sad** - Darker/dimmed appearance
- **Angry** - Shaking animation
- **Others** - Unique poses and expressions

## 📐 Panel Layout

Messages are grouped into comic panels:
- **3-4 messages per panel**
- Messages alternate left/center/right positions
- Speech bubble tails point toward characters
- Scrollable comic strip format

```
┌─────────────────────────────────┐
│ Panel 1                         │
│ ┌──────────┐  "Hello!"          │
│ │ [Cat]    │ ◀──────            │
│ └──────────┘                    │
│                "Hi there!"       │
│                ──────▶ ┌────────┐│
│                        │ [Dog]  ││
│                        └────────┘│
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Panel 2                         │
│ (next messages...)              │
└─────────────────────────────────┘
```

## 🎨 Backgrounds

Choose from 6 themed backgrounds:

| Background | Description |
|------------|-------------|
| **Room** 🏠 | Cozy living room interior |
| **Office** 🏢 | Professional office setting |
| **Outdoor** 🌳 | Natural outdoor scene |
| **Space** 🚀 | Futuristic space station |
| **Cafe** ☕ | Coffee shop atmosphere |
| **Beach** 🏖️ | Tropical beach scene |

## 💾 Database Schema

### New Fields in `irc_connections`:
```sql
comic_character VARCHAR(255) DEFAULT 'cat'
comic_view_mode VARCHAR(255) DEFAULT 'classic'
```

### New Fields in `irc_messages`:
```sql
emotion VARCHAR(255) DEFAULT 'normal'
gesture VARCHAR(255) DEFAULT 'none'
bubble_type VARCHAR(255) DEFAULT 'speech'
```

### New Fields in `irc_user_preferences`:
```sql
default_view_mode VARCHAR(255) DEFAULT 'classic'
comic_background VARCHAR(255) DEFAULT 'room'
show_emotions BOOLEAN DEFAULT true
```

## 🎯 Comparison: Classic vs Comic

### Classic IRC View
```
12:34 <Alice> Hello everyone!
12:35 <Bob> Hi Alice!
12:36 <Carol> Hey there!
```

### Comic Chat View
```
┌─────────────────────────────────────┐
│ ┌────┐  "Hello everyone!" 👋        │
│ │ 😊 │ ◀────────────                │
│ │Cat │                              │
│ └────┘                              │
│                    ┌────┐            │
│    "Hi Alice!"    │ 😐 │            │
│   ────────────▶   │Dog │            │
│                   └────┘            │
│ ┌──────┐  "Hey there!" 😊          │
│ │Robot │ ◀───────────               │
│ └──────┘                            │
└─────────────────────────────────────┘
```

## 🔧 Technical Details

### Components

**ComicChatView.vue** - Main comic rendering component
- Renders comic panels with speech bubbles
- Handles character positioning
- Manages bubble types and tails
- Auto-scrolling comic strip

**ComicCharacterSelector.vue** - Character/background picker
- Character selection grid
- Background theme chooser
- Preview of selected character

### Styling

- **Font**: Comic Sans MS (authentic 90s feel!)
- **Bubbles**: CSS-drawn with tails and shadows
- **Panels**: Black borders with comic book styling
- **Animations**: Bounce, shake, and emotion effects

### Character Images

Characters are SVG images with multiple emotion variants:
```
/images/comic/characters/
  cat-normal.svg
  cat-happy.svg
  cat-sad.svg
  cat-angry.svg
  (etc. for each emotion)
```

### Backgrounds

Background images for scenes:
```
/images/comic/backgrounds/
  room.jpg
  office.jpg
  outdoor.jpg
  space.jpg
  cafe.jpg
  beach.jpg
```

## 🎨 Creating Custom Characters

To add a new character:

1. Create SVG files for each emotion:
   - `charactername-normal.svg`
   - `charactername-happy.svg`
   - `charactername-sad.svg`
   - `charactername-angry.svg`
   - `charactername-surprised.svg`
   - `charactername-confused.svg`
   - `charactername-excited.svg`

2. Place in `/public/images/comic/characters/`

3. Add to character list in `ComicCharacterSelector.vue`:
   ```javascript
   characters: [
     { id: 'newchar', name: 'New Character', image: '/images/comic/characters/newchar-normal.svg' }
   ]
   ```

## 🚧 Current Limitations

- Character images are placeholders (need actual SVG art)
- Background images are placeholders (need actual scenes)
- No character creator/customization
- Emotions affect avatar only, not actual IRC server
- Comic panels are frontend-only visualization

## 🔮 Future Enhancements

### Planned Features
- [ ] **Character Creator** - Design your own avatar
- [ ] **Custom Backgrounds** - Upload your own scenes
- [ ] **Panel Export** - Save comic strips as images
- [ ] **Animation Library** - More character animations
- [ ] **Sound Effects** - Comic book sound effects (POW!, BAM!)
- [ ] **Multi-character Panels** - Multiple characters in one panel
- [ ] **Dynamic Composition** - AI-generated panel layouts
- [ ] **Stickers & Props** - Add items to panels
- [ ] **Effects** - Speed lines, impact stars, etc.
- [ ] **Print Mode** - PDF export for comic archives

### Advanced Features
- [ ] **Real-time Collaboration** - Draw on panels together
- [ ] **Character Library** - Share characters with community
- [ ] **Template Panels** - Pre-made comic layouts
- [ ] **Gesture Recognition** - Webcam-based emotion detection
- [ ] **Voice Acting** - TTS with character voices
- [ ] **Comic Themes** - Marvel, DC, Manga styles

## 📚 MS Comic Chat History

Microsoft Comic Chat was released in 1996 as part of Internet Explorer 3.0. It revolutionized IRC by:
- Adding visual representation to text chat
- Introducing avatars before they were common
- Making IRC accessible to non-technical users
- Creating the first "graphical IRC client"

**Fun Facts:**
- Developed by Microsoft Research
- Used IRC protocol underneath
- Characters designed by Jim Woodring
- Supported custom character creation
- Had built-in emotion detection
- Available on Windows 95/98/NT

This implementation is a modern tribute to that pioneering software! 🎉

## 🎮 Quick Start Guide

1. **Enable Comic View**
   ```
   Click 📖 icon in channel header
   ```

2. **Choose Character**
   ```
   Connection menu → Choose Character
   ```

3. **Set Emotion & Gesture**
   ```
   Select from emotion chips before sending
   ```

4. **Send Message**
   ```
   Type and press Enter
   ```

5. **Enjoy the Comics!**
   ```
   Watch your conversation unfold as a comic strip
   ```

## 🎨 Style Guide

### Bubble Positioning
- **Left** - First message in panel
- **Center** - Middle messages
- **Right** - Last message in panel

### Color Scheme
- **Bubbles** - White with black borders
- **Shout** - Light red (#ffe6e6)
- **Whisper** - Light grey (#f0f0f0)
- **Thought** - White, circular shape

### Typography
- **Font** - Comic Sans MS (or system fallback)
- **Speech** - 14px regular
- **Shout** - 1.2em bold
- **Whisper** - 0.9em italic

---

**Relive the 90s IRC experience with modern technology!** 🎨✨

Comic Chat brings fun, personality, and visual flair to IRC conversations. Whether you're nostalgic for the MS Comic Chat days or discovering it for the first time, enjoy expressing yourself in comic book form! 📚💬
